<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;

class MenuPublicoController extends Controller
{
    public function mostrar(string $slug): JsonResponse
    {
        $restaurante = Restaurante::query()
            ->where('slug', $slug)
            ->where('estado', 'activo')
            ->with([
                'categorias' => function ($query) {
                    $query
                        ->select(
                            'id_categoria',
                            'id_restaurante',
                            'nombre',
                            'descripcion',
                            'orden_menu'
                        )
                        ->where('activo', true)
                        ->orderBy('orden_menu')
                        ->orderBy('nombre');
                },
                'categorias.productos' => function ($query) {
                    $query
                        ->select(
                            'id_producto',
                            'id_restaurante',
                            'id_categoria',
                            'nombre',
                            'descripcion',
                            'precio',
                            'imagen_url',
                            'disponible'
                        )
                        ->where('disponible', true)
                        ->orderBy('nombre');
                },
            ])
            ->firstOrFail();

        return response()->json([
            'restaurante' => [
                'nombre' => $restaurante->nombre,
                'slug' => $restaurante->slug,
                'descripcion' => $restaurante->descripcion,
                'logo_url' => $restaurante->logo_url,
                'telefono' => $restaurante->telefono,
                'direccion' => $restaurante->direccion,
            ],
            'categorias' => $restaurante->categorias
                ->map(fn ($categoria) => [
                    'id_categoria' => $categoria->id_categoria,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion,
                    'productos' => $categoria->productos
                        ->map(fn ($producto) => [
                            'id_producto' => $producto->id_producto,
                            'nombre' => $producto->nombre,
                            'descripcion' => $producto->descripcion,
                            'precio' => $producto->precio,
                            'imagen_url' => $producto->imagen_url,
                        ])
                        ->values(),
                ])
                ->values(),
        ]);
    }
}
