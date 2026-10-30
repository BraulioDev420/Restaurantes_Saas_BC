<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y generar un token de acceso.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('correo', $datos['correo'])->first();

        if (
            !$usuario ||
            !Hash::check($datos['contrasena'], $usuario->contrasena) ||
            $usuario->estado !== 'activo'
        ) {
            return response()->json([
                'mensaje' => 'Credenciales incorrectas o usuario inactivo.',
            ], 401);
        }

        $token = $usuario->createToken('restaurantes-saas')->plainTextToken;

        // Consultar los restaurantes activos asociados al usuario.
        $restaurantes = $usuario->restaurantes()
            ->where('restaurantes.estado', 'activo')
            ->get([
                'restaurantes.id_restaurante',
                'restaurantes.nombre',
                'restaurantes.slug',
            ])
            ->map(function ($restaurante) {
                return [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'nombre' => $restaurante->nombre,
                    'slug' => $restaurante->slug,
                    'rol' => $restaurante->pivot->rol,
                ];
            })
            ->values();

        return response()->json([
            'mensaje' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'tipo_token' => 'Bearer',
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => $usuario->nombre,
                'correo' => $usuario->correo,
                'es_superadministrador' => $usuario->esSuperadministrador(),
            ],
            'restaurantes' => $restaurantes,
        ]);
    }

    /**
     * Consultar la identidad del usuario autenticado.
     */
    public function usuarioAutenticado(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => $request->user(),
        ]);
    }

    /**
     * Cerrar sesión revocando el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'mensaje' => 'Sesión cerrada correctamente.',
        ]);
    }
}
