<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRolRestaurante
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$rolesPermitidos
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'mensaje' => 'No autenticado.',
            ], 401);
        }

        if ($usuario->estado !== 'activo') {
            return response()->json([
                'mensaje' => 'Tu cuenta está inactiva.',
            ], 403);
        }

        $idRestaurante = $request->route('restaurante');

        if (! $idRestaurante) {
            return response()->json([
                'mensaje' => 'No se especificó el restaurante.',
            ], 400);
        }

        $restauranteUsuario = $usuario->restaurantes()
            ->where('restaurantes.id_restaurante', $idRestaurante)
            ->where('restaurantes.estado', 'activo')
            ->first();

        if (! $restauranteUsuario) {
            return response()->json([
                'mensaje' => 'No tienes acceso a este restaurante.',
            ], 403);
        }

        $rolUsuario = $restauranteUsuario->pivot->rol;

        if (! in_array($rolUsuario, $rolesPermitidos, true)) {
            return response()->json([
                'mensaje' => 'No tienes permisos para realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
