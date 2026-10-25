<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(string $restaurante): JsonResponse
    {
        $clientes = Cliente::query()
            ->where('id_restaurante', $restaurante)
            ->orderBy('nombre')
            ->get();

        return response()->json(['clientes' => $clientes]);
    }

    public function store(Request $request, string $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => [
                'required',
                'string',
                'max:30',
                Rule::unique('clientes', 'telefono')
                    ->where('id_restaurante', $restaurante),
            ],
            'correo' => ['nullable', 'email', 'max:150'],
        ]);

        $datos['id_restaurante'] = (int) $restaurante;

        $cliente = Cliente::create($datos);

        return response()->json([
            'mensaje' => 'Cliente creado correctamente.',
            'cliente' => $cliente,
        ], 201);
    }

    public function show(string $restaurante, string $cliente): JsonResponse
    {
        $registro = Cliente::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_cliente', $cliente)
            ->firstOrFail();

        return response()->json(['cliente' => $registro]);
    }

    public function update(
        Request $request,
        string $restaurante,
        string $cliente
    ): JsonResponse {
        $registro = Cliente::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_cliente', $cliente)
            ->firstOrFail();

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'telefono' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('clientes', 'telefono')
                    ->where('id_restaurante', $restaurante)
                    ->ignore($registro->id_cliente, 'id_cliente'),
            ],
            'correo' => ['sometimes', 'nullable', 'email', 'max:150'],
        ]);

        $registro->update($datos);

        return response()->json([
            'mensaje' => 'Cliente actualizado correctamente.',
            'cliente' => $registro->fresh(),
        ]);
    }

    public function destroy(string $restaurante, string $cliente): JsonResponse
    {
        $registro = Cliente::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_cliente', $cliente)
            ->firstOrFail();

        $registro->delete();

        return response()->json([
            'mensaje' => 'Cliente eliminado correctamente.',
        ]);
    }
}
