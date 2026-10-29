<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarSuperadministrador
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json([
                'mensaje' => 'Debes iniciar sesión.',
            ], 401);
        }

        if (
            $usuario->estado !== 'activo' ||
            ! $usuario->esSuperadministrador()
        ) {
            return response()->json([
                'mensaje' => 'No tienes permisos de superadministrador.',
            ], 403);
        }

        return $next($request);
    }
}