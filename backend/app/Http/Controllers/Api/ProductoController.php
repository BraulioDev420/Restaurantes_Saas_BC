<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(Request $request, string $restaurante): JsonResponse
    {
        $productos = Producto::query()
            ->where('id_restaurante', $restaurante)
            ->with('categoria:id_categoria,nombre')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'productos' => $productos,
        ]);
    }

    public function store(Request $request, string $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'id_categoria' => [
                'required',
                'integer',
                Rule::exists('categorias', 'id_categoria')
                    ->where('id_restaurante', $restaurante)
                    ->where('activo', true),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'imagen_url' => ['nullable', 'url', 'max:500'],
            'disponible' => ['sometimes', 'boolean'],
        ]);

        $datos['id_restaurante'] = (int) $restaurante;

        $producto = Producto::create($datos);

        return response()->json([
            'mensaje' => 'Producto creado correctamente.',
            'producto' => $producto->load('categoria:id_categoria,nombre'),
        ], 201);
    }

    public function show(string $restaurante, string $producto): JsonResponse
    {
        $registro = Producto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_producto', $producto)
            ->with('categoria:id_categoria,nombre')
            ->firstOrFail();

        return response()->json([
            'producto' => $registro,
        ]);
    }

    public function update(
        Request $request,
        string $restaurante,
        string $producto
    ): JsonResponse {
        $registro = Producto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_producto', $producto)
            ->firstOrFail();

        $datos = $request->validate([
            'id_categoria' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categorias', 'id_categoria')
                    ->where('id_restaurante', $restaurante)
                    ->where('activo', true),
            ],
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'imagen_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'disponible' => ['sometimes', 'boolean'],
        ]);

        $registro->update($datos);

        return response()->json([
            'mensaje' => 'Producto actualizado correctamente.',
            'producto' => $registro->fresh()->load('categoria:id_categoria,nombre'),
        ]);
    }

    public function destroy(string $restaurante, string $producto): JsonResponse
    {
        $registro = Producto::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_producto', $producto)
            ->firstOrFail();

        $registro->update(['disponible' => false]);

        return response()->json([
            'mensaje' => 'Producto desactivado correctamente.',
        ]);
    }
}
