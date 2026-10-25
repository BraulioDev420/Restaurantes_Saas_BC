<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function index(string $restaurante)
    {
        $pagos = Pago::where('id_restaurante', $restaurante)
            ->with([
                'pedido:id_pedido,id_restaurante,numero_pedido,total,estado',
                'verificadoPor:id_usuario,nombre',
            ])
            ->orderByDesc('creado_en')
            ->get();

        return response()->json($pagos);
    }

    public function store(Request $request, string $restaurante)
    {
        $datos = $request->validate([
            'id_pedido'     => [
                'required',
                'integer',
                Rule::exists('pedidos', 'id_pedido')
                    ->where('id_restaurante', $restaurante),
            ],
            'metodo_pago'   => [
                'required',
                Rule::in([
                    'efectivo',
                    'transferencia',
                    'nequi',
                    'bre-b',
                    'tarjeta',
                    'otro',
                ]),
            ],
            'monto'         => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'comprobante'   => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        $pago = DB::transaction(function () use (
            $datos,
            $restaurante,
            $request
        ) {
            $pedido = Pedido::where('id_restaurante', $restaurante)
                ->where('id_pedido', $datos['id_pedido'])
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($pedido->estado, ['cancelado', 'entregado'])) {
                abort(422, 'No se pueden registrar pagos para pedidos cancelados o entregados.');
            }

            $totalComprometido = Pago::where('id_restaurante', $restaurante)
                ->where('id_pedido', $pedido->id_pedido)
                ->whereIn('estado', ['pendiente', 'verificado'])
                ->sum('monto');

            $totalPedidoCentavos       = (int) round((float) $pedido->total * 100);
            $totalComprometidoCentavos = (int) round((float) $totalComprometido * 100);
            $montoNuevoCentavos        = (int) round((float) $datos['monto'] * 100);

            $saldoCentavos = $totalPedidoCentavos - $totalComprometidoCentavos;

            if ($montoNuevoCentavos > $saldoCentavos) {
                abort(
                    422,
                    'El monto del pago supera el saldo disponible del pedido. Saldo disponible: ' .
                    number_format(max(0, $saldoCentavos) / 100, 2, '.', '')
                );
            }
            $rutaComprobante = null;

            if ($request->hasFile('comprobante')) {
                $rutaComprobante = $request->file('comprobante')
                    ->store("comprobantes/{$restaurante}", 'local');
            }
            return Pago::create([
                'id_restaurante'  => $restaurante,
                'id_pedido'       => $pedido->id_pedido,
                'metodo_pago'     => $datos['metodo_pago'],
                'monto'           => $datos['monto'],
                'comprobante_url' => $rutaComprobante,
                'observaciones'   => $datos['observaciones'] ?? null,
                'estado'          => 'pendiente',
            ]);
        });

        return response()->json([
            'mensaje' => 'Pago registrado correctamente y pendiente de verificación.',
            'pago'    => $pago->load('pedido'),
        ], 201);
    }

    public function descargarComprobante(string $restaurante, string $pago)
    {
        $registro = Pago::where('id_restaurante', $restaurante)
            ->where('id_pago', $pago)
            ->firstOrFail();

        if (! $registro->comprobante_url) {
            abort(404, 'Este pago no tiene comprobante.');
        }

        if (! Storage::disk('local')->exists($registro->comprobante_url)) {
            abort(404, 'El archivo del comprobante no está disponible.');
        }

        return Storage::disk('local')->download($registro->comprobante_url);
    }

    public function show(string $restaurante, string $pago)
    {
        $registro = Pago::where('id_restaurante', $restaurante)
            ->where('id_pago', $pago)
            ->with([
                'pedido',
                'verificadoPor:id_usuario,nombre',
            ])
            ->firstOrFail();

        return response()->json($registro);
    }

    public function verificar(Request $request, string $restaurante, string $pago)
    {
        $datos = $request->validate([
            'estado'        => ['required', Rule::in(['verificado', 'rechazado'])],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        $registro = DB::transaction(function () use (
            $request,
            $restaurante,
            $pago,
            $datos
        ) {
            $registro = Pago::where('id_restaurante', $restaurante)
                ->where('id_pago', $pago)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registro->estado !== 'pendiente') {
                abort(422, 'Solo se pueden verificar pagos que estén pendientes.');
            }

            $registro->estado            = $datos['estado'];
            $registro->id_verificado_por = $request->user()->id_usuario;
            $registro->verificado_en     = now();

            if (array_key_exists('observaciones', $datos)) {
                $registro->observaciones = $datos['observaciones'];
            }

            $registro->save();

            return $registro->load('verificadoPor:id_usuario,nombre');
        });

        return response()->json([
            'mensaje' => 'Estado del pago actualizado correctamente.',
            'pago'    => $registro,
        ]);
    }
}
