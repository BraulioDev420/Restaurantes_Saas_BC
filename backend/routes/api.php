<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\MenuPublicoController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\PedidoPublicoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/estado', function () {
    return response()->json([
        'mensaje' => 'API funcionando correctamente',
        'estado'  => 'ok',
    ]);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::get(
    '/menu/{slug}',
    [MenuPublicoController::class, 'mostrar']
);
Route::post(
    '/pedido-publico/{slug}',
    [PedidoPublicoController::class, 'store']
)->middleware('throttle:10,1');

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
            if (! $restauranteUsuario) {
                return response()->json([
                    'mensaje' => 'No tienes acceso a este restaurante.',
                ], 403);
            }

            return response()->json([
                'mensaje'     => 'Acceso autorizado.',
                'restaurante' => (int) $restaurante,
                'rol'         => $restauranteUsuario->pivot->rol,
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

    // CRUD de productos, protegido por rol y pertenencia al restaurante.
    Route::get(
        '/restaurantes/{restaurante}/productos',
        [ProductoController::class, 'index']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post(
        '/restaurantes/{restaurante}/productos',
        [ProductoController::class, 'store']
    )->middleware('rol.restaurante:administrador');

    Route::get(
        '/restaurantes/{restaurante}/productos/{producto}',
        [ProductoController::class, 'show']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::put(
        '/restaurantes/{restaurante}/productos/{producto}',
        [ProductoController::class, 'update']
    )->middleware('rol.restaurante:administrador');

    Route::patch(
        '/restaurantes/{restaurante}/productos/{producto}',
        [ProductoController::class, 'update']
    )->middleware('rol.restaurante:administrador');

    Route::delete(
        '/restaurantes/{restaurante}/productos/{producto}',
        [ProductoController::class, 'destroy']
    )->middleware('rol.restaurante:administrador');

    // CRUD de clientes, protegido por rol y pertenencia al restaurante.
    Route::get(
        '/restaurantes/{restaurante}/clientes',
        [ClienteController::class, 'index']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post(
        '/restaurantes/{restaurante}/clientes',
        [ClienteController::class, 'store']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::get(
        '/restaurantes/{restaurante}/clientes/{cliente}',
        [ClienteController::class, 'show']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::put(
        '/restaurantes/{restaurante}/clientes/{cliente}',
        [ClienteController::class, 'update']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::patch(
        '/restaurantes/{restaurante}/clientes/{cliente}',
        [ClienteController::class, 'update']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::delete(
        '/restaurantes/{restaurante}/clientes/{cliente}',
        [ClienteController::class, 'destroy']
    )->middleware('rol.restaurante:administrador');

    // Consultar y crear pedidos.
    Route::get(
        '/restaurantes/{restaurante}/pedidos',
        [PedidoController::class, 'index']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post(
        '/restaurantes/{restaurante}/pedidos',
        [PedidoController::class, 'store']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::get(
        '/restaurantes/{restaurante}/pedidos/{pedido}',
        [PedidoController::class, 'show']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::patch(
        '/restaurantes/{restaurante}/pedidos/{pedido}/estado',
        [PedidoController::class, 'actualizarEstado']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::get(
        '/restaurantes/{restaurante}/pedidos/{pedido}/historial',
        [PedidoController::class, 'historial']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    // Pagos
    Route::get('/restaurantes/{restaurante}/pagos', [PagoController::class, 'index'])
        ->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post('/restaurantes/{restaurante}/pagos', [PagoController::class, 'store'])
        ->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::get('/restaurantes/{restaurante}/pagos/{pago}', [PagoController::class, 'show'])
        ->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::patch('/restaurantes/{restaurante}/pagos/{pago}/verificar', [PagoController::class, 'verificar'])
        ->middleware('rol.restaurante:administrador,cajero');

    Route::get(
        '/restaurantes/{restaurante}/pagos/{pago}/comprobante',
        [PagoController::class, 'descargarComprobante']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    // Gastos: consulta para los roles operativos; gestión restringida.
    Route::get(
        '/restaurantes/{restaurante}/gastos',
        [GastoController::class, 'index']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::post(
        '/restaurantes/{restaurante}/gastos',
        [GastoController::class, 'store']
    )->middleware('rol.restaurante:administrador,cajero');

    Route::get(
        '/restaurantes/{restaurante}/gastos/{gasto}',
        [GastoController::class, 'show']
    )->middleware('rol.restaurante:administrador,empleado,cajero');

    Route::put(
        '/restaurantes/{restaurante}/gastos/{gasto}',
        [GastoController::class, 'update']
    )->middleware('rol.restaurante:administrador,cajero');

    Route::patch(
        '/restaurantes/{restaurante}/gastos/{gasto}',
        [GastoController::class, 'update']
    )->middleware('rol.restaurante:administrador,cajero');

    Route::delete(
        '/restaurantes/{restaurante}/gastos/{gasto}',
        [GastoController::class, 'destroy']
    )->middleware('rol.restaurante:administrador');

    // Reportes financieros y estadísticas del restaurante.
    Route::get(
        '/restaurantes/{restaurante}/reportes/resumen',
        [ReporteController::class, 'resumen']
    )->middleware('rol.restaurante:administrador');

    // Gestión de usuarios del restaurante: solo administradores.
    Route::middleware('rol.restaurante:administrador')->group(function () {
        Route::get(
            '/restaurantes/{restaurante}/usuarios',
            [UsuarioController::class, 'index']
        );

        Route::post(
            '/restaurantes/{restaurante}/usuarios',
            [UsuarioController::class, 'store']
        );

        Route::put(
            '/restaurantes/{restaurante}/usuarios/{usuario}',
            [UsuarioController::class, 'update']
        );

        Route::patch(
            '/restaurantes/{restaurante}/usuarios/{usuario}',
            [UsuarioController::class, 'update']
        );

        Route::delete(
            '/restaurantes/{restaurante}/usuarios/{usuario}',
            [UsuarioController::class, 'destroy']
        );
    });

    //SUPERADMIN
    Route::prefix('admin')
        ->middleware('superadministrador')
        ->group(function () {
            Route::get(
                '/restaurantes',
                [\App\Http\Controllers\Api\SuperadminRestauranteController::class, 'index']
            );

            Route::post(
                '/restaurantes',
                [\App\Http\Controllers\Api\SuperadminRestauranteController::class, 'store']
            );
            Route::patch(
                '/restaurantes/{id}/estado',
                [
                    \App\Http\Controllers\Api\SuperadminRestauranteController::class,
                    'cambiarEstado',
                ]
            )->whereNumber('id');
            Route::get('/yo', function (\Illuminate\Http\Request $request) {
                $usuario = $request->user();

                return response()->json([
                    'usuario' => [
                        'id_usuario'            => $usuario->id_usuario,
                        'nombre'                => $usuario->nombre,
                        'correo'                => $usuario->correo,
                        'es_superadministrador' => true,
                    ],
                ]);
            });

        });

});
