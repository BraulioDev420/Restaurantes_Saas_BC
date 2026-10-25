<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaController;

Route::get('/estado', function () {
    return response()->json([
        'mensaje' => 'API funcionando correctamente',
        'estado' => 'ok',
    ]);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {

    // Consultar el usuario autenticado.
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/usuario', [
        AuthController::class,
        'usuarioAutenticado',
    ]);

    // Listar únicamente los restaurantes asociados al usuario.
    Route::get('/restaurantes', function (Request $request) {
        $restaurantes = $request->user()
            ->restaurantes()
            ->where('restaurantes.estado', 'activo')
            ->select(
                'restaurantes.id_restaurante',
                'restaurantes.nombre',
                'restaurantes.slug',
                'restaurantes.descripcion',
                'restaurantes.logo_url',
                'restaurantes.estado'
            )
            ->get();

        return response()->json([
            'restaurantes' => $restaurantes,
        ]);
    });

    // Ruta de prueba: solo los administradores del restaurante pueden acceder.
    Route::get(
        '/restaurantes/{restaurante}/administracion',
        function (Request $request, string $restaurante) {
            $restauranteUsuario = $request->user()
                ->restaurantes()
                ->where(
                    'restaurantes.id_restaurante',
                    $restaurante
                )
                ->where('restaurantes.estado', 'activo')
                ->first();

            // Comprobación adicional para evitar errores si no existe
            // una asociación válida.
            if (!$restauranteUsuario) {
                return response()->json([
                    'mensaje' => 'No tienes acceso a este restaurante.',
                ], 403);
            }

            return response()->json([
                'mensaje' => 'Acceso autorizado.',
                'restaurante' => (int) $restaurante,
                'rol' => $restauranteUsuario->pivot->rol,
            ]);
        }
    )->middleware('rol.restaurante:administrador');

    // Cerrar la sesión actual.
    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);


    
    // CRUD de categorías, protegido por rol y pertenencia al restaurante.
    Route::get(
        '/restaurantes/{restaurante}/categorias',
        [CategoriaController::class, 'index']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post(
        '/restaurantes/{restaurante}/categorias',
        [CategoriaController::class, 'store']
    )->middleware('rol.restaurante:administrador');

    Route::get(
        '/restaurantes/{restaurante}/categorias/{categoria}',
        [CategoriaController::class, 'show']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::put(
        '/restaurantes/{restaurante}/categorias/{categoria}',
        [CategoriaController::class, 'update']
    )->middleware('rol.restaurante:administrador');

    Route::patch(
        '/restaurantes/{restaurante}/categorias/{categoria}',
        [CategoriaController::class, 'update']
    )->middleware('rol.restaurante:administrador');

    Route::delete(
        '/restaurantes/{restaurante}/categorias/{categoria}',
        [CategoriaController::class, 'destroy']
    )->middleware('rol.restaurante:administrador');


});
