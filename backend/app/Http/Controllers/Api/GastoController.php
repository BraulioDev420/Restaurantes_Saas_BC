<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(string $restaurante): JsonResponse
    {
        $gastos = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->orderByDesc('fecha_gasto')
            ->orderByDesc('id_gasto')
            ->paginate(15);

        return response()->json($gastos);
    }

    public function store(Request $request, string $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'max:50'],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'fecha_gasto' => ['required', 'date_format:Y-m-d'],
            'comprobante_url' => ['nullable', 'url', 'max:500'],
        ]);

        $datos['id_restaurante'] = (int) $restaurante;
        $datos['id_creado_por'] = (int) $request->user()->id_usuario;

        $gasto = Gasto::create($datos);

        return response()->json([
            'mensaje' => 'Gasto registrado correctamente.',
            'gasto' => $gasto,
        ], 201);
    }

    public function show(string $restaurante, string $gasto): JsonResponse
    {
        $registro = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_gasto', $gasto)
            ->firstOrFail();

        return response()->json(['gasto' => $registro]);
    }

    public function update(
        Request $request,
        string $restaurante,
        string $gasto
    ): JsonResponse {
        $registro = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_gasto', $gasto)
            ->firstOrFail();

        $datos = $request->validate([
            'descripcion' => ['sometimes', 'required', 'string', 'max:255'],
            'categoria' => ['sometimes', 'required', 'string', 'max:50'],
            'monto' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'fecha_gasto' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'comprobante_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $registro->update($datos);

        return response()->json([
            'mensaje' => 'Gasto actualizado correctamente.',
            'gasto' => $registro->fresh(),
        ]);
    }

    public function destroy(string $restaurante, string $gasto): JsonResponse
    {
        $registro = Gasto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_gasto', $gasto)
            ->firstOrFail();

        $registro->delete();

        return response()->json([
            'mensaje' => 'Gasto eliminado correctamente.',
        ]);
    }
}
