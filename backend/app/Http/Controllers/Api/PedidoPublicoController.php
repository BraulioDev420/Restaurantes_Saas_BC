<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoPublicoController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $restaurante = Restaurante::query()
            ->where('slug', $slug)
            ->where('estado', 'activo')
            ->firstOrFail();

        $restauranteId = (int) $restaurante->id_restaurante;

        $datos = $request->validate([
            'nombre_cliente'           => ['required', 'string', 'max:150'],
            'telefono_cliente'         => [
                'required',
                'string',
                'regex:/^[0-9]{7,15}$/',
                'max:15',
            ],
            'correo_cliente'           => ['nullable', 'email', 'max:150'],

            'tipo_entrega'             => [
                'required',
                Rule::in(['domicilio', 'recoger', 'mesa']),
            ],

            'direccion_entrega'        => [
                'nullable',
                'string',
                'max:255',
            ],

            'notas'                    => ['nullable', 'string', 'max:1000'],

            'detalles'                 => ['required', 'array', 'min:1', 'max:50'],

            'detalles.*.id_producto'   => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id_producto')
                    ->where('id_restaurante', $restauranteId)
                    ->where('disponible', true),
            ],

            'detalles.*.cantidad'      => [
                'required',
                'integer',
                'min:1',
                'max:100',
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

        $pedido = DB::transaction(function () use (
            $datos,
            $restauranteId
        ) {
            $productosIds = collect($datos['detalles'])
                ->pluck('id_producto');

            $productos = Producto::query()
                ->where('id_restaurante', $restauranteId)
                ->where('disponible', true)
                ->whereIn('id_producto', $productosIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');

            if (
                $productos->count()
                !== $productosIds->unique()->count()
            ) {
                throw ValidationException::withMessages([
                    'detalles' => [
                        'Uno o más productos ya no están disponibles.',
                    ],
                ]);
            }

            // Calcular importes usando los precios guardados en MySQL.
            // Se trabaja en centavos para evitar errores de redondeo.
            $subtotalCentavos = 0;

            foreach ($datos['detalles'] as $detalle) {
                $producto = $productos->get(
                    $detalle['id_producto']
                );

                $precioCentavos = (int) round(
                    (float) $producto->precio * 100
                );

                $subtotalCentavos +=
                    $precioCentavos * $detalle['cantidad'];
            }

            // No se acepta el costo de domicilio del cliente.
            // Por ahora es cero hasta configurar una tarifa del restaurante.
            $costoDomicilioCentavos  = 0;

            $pedido = Pedido::create([
                'id_restaurante'    => $restauranteId,
                'id_cliente'        => null,
                'numero_pedido'     => 'PED-' . bin2hex(random_bytes(8)),
                'nombre_cliente'    => $datos['nombre_cliente'],
                'telefono_cliente'  => $datos['telefono_cliente'],
                'correo_cliente'    => $datos['correo_cliente'] ?? null,
                'tipo_entrega'      => $datos['tipo_entrega'],
                'direccion_entrega' =>
                $datos['direccion_entrega'] ?? null,
                'notas'             => $datos['notas'] ?? null,
                'subtotal'          => $subtotalCentavos / 100,
                'costo_domicilio'   =>
                $costoDomicilioCentavos / 100,
                'total'             => (
                    $subtotalCentavos + $costoDomicilioCentavos
                ) / 100,
                'estado'            => 'pendiente',
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $producto = $productos->get(
                    $detalle['id_producto']
                );

                $cantidad = $detalle['cantidad'];

                $precioCentavos = (int) round(
                    (float) $producto->precio * 100
                );

                $pedido->detalles()->create([
                    'id_restaurante'  => $restauranteId,
                    'id_producto'     => $producto->id_producto,
                    'nombre_producto' => $producto->nombre,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioCentavos / 100,
                    'subtotal'        => (
                        $precioCentavos * $cantidad
                    ) / 100,
                    'observaciones'   =>
                    $detalle['observaciones'] ?? null,
                ]);
            }

            return $pedido;
        });

        return response()->json([
            'mensaje' => 'Pedido creado correctamente.',
            'pedido'  => $pedido->load('detalles'),
        ], 201);
    }
}
