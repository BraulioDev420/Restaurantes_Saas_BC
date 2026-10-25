<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    /**
     * Listar las categorías de un restaurante.
     */
    public function index(Request $request, string $restaurante): JsonResponse
    {
        $categorias = Categoria::query()
            ->where('id_restaurante', $restaurante)
            ->orderBy('orden_menu')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'categorias' => $categorias,
        ]);
    }

    /**
     * Crear una categoría.
     */
    public function store(Request $request, string $restaurante): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')
                    ->where('id_restaurante', $restaurante),
            ],
            'descripcion' => ['nullable', 'string'],
            'orden_menu' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $datos['id_restaurante'] = (int) $restaurante;

        $categoria = Categoria::create($datos);

        return response()->json([
            'mensaje' => 'Categoría creada correctamente.',
            'categoria' => $categoria,
        ], 201);
    }

    /**
     * Consultar una categoría específica.
     */
    public function show(string $restaurante, string $categoria): JsonResponse
    {
        $registro = Categoria::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_categoria', $categoria)
            ->firstOrFail();

        return response()->json([
            'categoria' => $registro,
        ]);
    }

    /**
     * Actualizar una categoría.
     */
    public function update(
        Request $request,
        string $restaurante,
        string $categoria
    ): JsonResponse {
        $registro = Categoria::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_categoria', $categoria)
            ->firstOrFail();

        $datos = $request->validate([
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')
                    ->where('id_restaurante', $restaurante)
                    ->ignore($registro->id_categoria, 'id_categoria'),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'orden_menu' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $registro->update($datos);

        return response()->json([
            'mensaje' => 'Categoría actualizada correctamente.',
            'categoria' => $registro->fresh(),
        ]);
    }

    /**
     * Desactivar una categoría sin borrarla de la base de datos.
     */
    public function destroy(string $restaurante, string $categoria): JsonResponse
    {
        $registro = Categoria::query()
            ->where('id_restaurante', $restaurante)
            ->where('id_categoria', $categoria)
            ->firstOrFail();

        $registro->update([
            'activo' => false,
        ]);

        return response()->json([
            'mensaje' => 'Categoría desactivada correctamente.',
        ]);
    }
}
