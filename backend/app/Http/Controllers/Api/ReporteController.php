<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteController extends Controller
{
    public function resumen(Request $request, string $restaurante): JsonResponse
    {
        $fechas = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $desde = $fechas['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $fechas['hasta'] ?? now()->toDateString();

        if ($desde > $hasta) {
            throw ValidationException::withMessages([
                'desde' => 'La fecha inicial no puede ser posterior a la fecha final.',
            ]);
        }

        $ingresos = Pago::query()
            ->where('id_restaurante', $restaurante)
            ->where('estado', 'verificado')
            ->whereBetween(DB::raw('DATE(verificado_en)'), [$desde, $hasta])
            ->sum('monto');

        $gastos = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->whereBetween('fecha_gasto', [$desde, $hasta])
            ->sum('monto');

        $pedidos = Pedido::query()
            ->where('id_restaurante', $restaurante)
            ->whereBetween(DB::raw('DATE(creado_en)'), [$desde, $hasta]);

        $cantidadPedidos = (clone $pedidos)->count();

        $valorPedidos = (clone $pedidos)
            ->whereNotIn('estado', ['cancelado', 'cancelada'])
            ->sum('total');

        $porEstado = (clone $pedidos)
            ->select('estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        $gastosPorCategoria = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->whereBetween('fecha_gasto', [$desde, $hasta])
            ->select('categoria', DB::raw('SUM(monto) as total'))
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'periodo' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'resumen' => [
                'ingresos_verificados' => number_format((float) $ingresos, 2, '.', ''),
                'gastos_registrados' => number_format((float) $gastos, 2, '.', ''),
                'balance_estimado' => number_format(
                    (float) $ingresos - (float) $gastos,
                    2,
                    '.',
                    ''
                ),
                'cantidad_pedidos' => $cantidadPedidos,
                'valor_pedidos_no_cancelados' => number_format(
                    (float) $valorPedidos,
                    2,
                    '.',
                    ''
                ),
            ],
            'pedidos_por_estado' => $porEstado,
            'gastos_por_categoria' => $gastosPorCategoria,
        ]);
    }
}
