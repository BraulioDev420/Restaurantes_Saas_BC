<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function index(string $restaurante): JsonResponse
    {
        $pedidos = Pedido::query()
            ->where('id_restaurante', $restaurante)
            ->with('detalles')
            ->orderByDesc('creado_en')
            ->get();

        return response()->json(['pedidos' => $pedidos]);
    }

    public function store(Request $request, string $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'id_cliente' => [
                'nullable',
                'integer',
                Rule::exists('clientes', 'id_cliente')
                    ->where('id_restaurante', $restaurante),
            ],
            'nombre_cliente' => ['required', 'string', 'max:150'],
            'telefono_cliente' => ['required', 'string', 'max:30'],
            'correo_cliente' => ['nullable', 'email', 'max:150'],
            'tipo_entrega' => [
                'required',
                Rule::in(['domicilio', 'recoger', 'mesa']),
            ],
            'direccion_entrega' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
            'costo_domicilio' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id_producto')
                    ->where('id_restaurante', $restaurante)
                    ->where('disponible', true),
            ],
            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'detalles.*.observaciones' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        if (
            $datos['tipo_entrega'] === 'domicilio'
            && empty($datos['direccion_entrega'])
        ) {
            throw ValidationException::withMessages([
                'direccion_entrega' => [
                    'La dirección es obligatoria para pedidos a domicilio.',
                ],
            ]);
        }

        $pedido = DB::transaction(function () use ($datos, $restaurante) {
            $productosIds = collect($datos['detalles'])
                ->pluck('id_producto');

            $productos = Producto::query()
                ->where('id_restaurante', $restaurante)
                ->where('disponible', true)
                ->whereIn('id_producto', $productosIds)
                ->get()
                ->keyBy('id_producto');

            if ($productos->count() !== $productosIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'detalles' => [
                        'Uno o más productos no están disponibles.',
                    ],
                ]);
            }

            $subtotal = 0;

            foreach ($datos['detalles'] as $detalle) {
                $producto = $productos->get($detalle['id_producto']);

                $subtotal += (float) $producto->precio
                    * $detalle['cantidad'];
            }

            $costoDomicilio = (float) ($datos['costo_domicilio'] ?? 0);

            $pedido = Pedido::create([
                'id_restaurante' => (int) $restaurante,
                'id_cliente' => $datos['id_cliente'] ?? null,
                'numero_pedido' => 'PED-' . bin2hex(random_bytes(8)),
                'nombre_cliente' => $datos['nombre_cliente'],
                'telefono_cliente' => $datos['telefono_cliente'],
                'correo_cliente' => $datos['correo_cliente'] ?? null,
                'tipo_entrega' => $datos['tipo_entrega'],
                'direccion_entrega' => $datos['direccion_entrega'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'subtotal' => $subtotal,
                'costo_domicilio' => $costoDomicilio,
                'total' => $subtotal + $costoDomicilio,
                'estado' => 'pendiente',
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $producto = $productos->get($detalle['id_producto']);
                $cantidad = $detalle['cantidad'];
                $precio = (float) $producto->precio;

                $pedido->detalles()->create([
                    'id_restaurante' => (int) $restaurante,
                    'id_producto' => $producto->id_producto,
                    'nombre_producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $precio * $cantidad,
                    'observaciones' => $detalle['observaciones'] ?? null,
                ]);
            }

            return $pedido;
        });

        return response()->json([
            'mensaje' => 'Pedido creado correctamente.',
            'pedido' => $pedido->load('detalles'),
        ], 201);
    }

    public function show(string $restaurante, string $pedido): JsonResponse
    {
        $registro = Pedido::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_pedido', $pedido)
            ->with('detalles')
            ->firstOrFail();

        return response()->json(['pedido' => $registro]);
    }

public function actualizarEstado(
    Request $request,
    string $restaurante,
    string $pedido
): JsonResponse {
    $datos = $request->validate([
        'estado' => [
            'required',
            'string',
            Rule::in([
                'pendiente',
                'preparando',
                'listo',
                'en_camino',
                'entregado',
                'cancelado',
            ]),
        ],
        'notas' => ['nullable', 'string', 'max:1000'],
    ]);

    $registro = DB::transaction(function () use (
        $datos,
        $restaurante,
        $pedido,
        $request
    ) {
        $registro = Pedido::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_pedido', $pedido)
            ->lockForUpdate()
            ->firstOrFail();

        $estadoActual = $registro->estado;
        $estadoNuevo = $datos['estado'];

        $transiciones = [
            'pendiente' => ['preparando', 'cancelado'],
            'preparando' => ['listo', 'cancelado'],
            'listo' => $registro->tipo_entrega === 'domicilio'
                ? ['en_camino', 'cancelado']
                : ['entregado', 'cancelado'],
            'en_camino' => ['entregado'],
            'entregado' => [],
            'cancelado' => [],
        ];

        $permitidos = $transiciones[$estadoActual] ?? [];

        if (!in_array($estadoNuevo, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => [
                    "No se puede cambiar el pedido de '{$estadoActual}' a '{$estadoNuevo}'.",
                ],
            ]);
        }

        $registro->update([
            'estado' => $estadoNuevo,
        ]);

        $registro->historialEstados()->create([
            'id_restaurante' => (int) $restaurante,
            'id_cambiado_por' => $request->user()->id_usuario,
            'estado' => $estadoNuevo,
            'notas' => $datos['notas'] ?? null,
        ]);

        return $registro->load('detalles', 'historialEstados');
    });

    return response()->json([
        'mensaje' => 'Estado del pedido actualizado correctamente.',
        'pedido' => $registro,
    ]);
}

public function historial(
    string $restaurante,
    string $pedido
): JsonResponse {
    $registro = Pedido::query()
        ->where('id_restaurante', $restaurante)
        ->where('id_pedido', $pedido)
        ->firstOrFail();

    $historial = $registro->historialEstados()
        ->with('cambiadoPor:id_usuario,nombre')
        ->orderBy('creado_en')
        ->get();

    return response()->json([
        'id_pedido' => $registro->id_pedido,
        'historial' => $historial,
    ]);
}

}
