<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurante;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    private const ROLES = ['administrador', 'empleado', 'cajero'];

    /**
     * Listar usuarios asociados al restaurante.
     */
    public function index(string $restaurante): JsonResponse
    {
        $usuarios = $this->restaurante($restaurante)
            ->usuarios()
            ->select(
                'usuarios.id_usuario',
                'usuarios.nombre',
                'usuarios.correo',
                'usuarios.estado'
            )
            ->orderBy('usuarios.nombre')
            ->get()
            ->map(function ($usuario) {
                return [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre'     => $usuario->nombre,
                    'correo'     => $usuario->correo,
                    'estado'     => $usuario->estado,
                    'rol'        => $usuario->pivot->rol,
                    'creado_en'  => $usuario->pivot->creado_en,
                ];
            });

        return response()->json(['usuarios' => $usuarios]);
    }

    /**
     * Crear un usuario y asociarlo al restaurante.
     */
    public function store(Request $request, string $restaurante): JsonResponse
    {
        $restauranteModel = $this->restaurante($restaurante);

        $datos = $request->validate([
            'nombre'     => ['required', 'string', 'max:150'],
            'correo'     => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'contrasena' => ['required', 'string', 'min:8', 'max:255'],
            'rol'        => ['required', Rule::in(self::ROLES)],
        ]);

        $usuario = DB::transaction(function () use ($datos, $restauranteModel) {
            $usuario = Usuario::create([
                'nombre'     => $datos['nombre'],
                'correo'     => $datos['correo'],
                'contrasena' => Hash::make($datos['contrasena']),
                'estado'     => 'activo',
            ]);

            $restauranteModel->usuarios()->attach($usuario->id_usuario, [
                'rol' => $datos['rol'],
            ]);

            return $usuario;
        });

        return response()->json([
            'mensaje' => 'Usuario creado correctamente.',
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre'     => $usuario->nombre,
                'correo'     => $usuario->correo,
                'estado'     => $usuario->estado,
                'rol'        => $datos['rol'],
            ],
        ], 201);
    }

    /**
     * Actualizar nombre, correo y rol dentro del restaurante.
     */
    public function update(
        Request $request,
        string $restaurante,
        string $usuario
    ): JsonResponse {
        $restauranteModel = $this->restaurante($restaurante);
        $usuarioModel     = $this->usuarioDelRestaurante($restauranteModel, $usuario);

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'correo' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('usuarios', 'correo')->ignore(
                    $usuarioModel->id_usuario,
                    'id_usuario'
                ),
            ],
            'rol'    => ['sometimes', 'required', Rule::in(self::ROLES)],

        ]);

        DB::transaction(function () use (
            $datos,
            $usuarioModel,
            $restauranteModel,
            $request
        ) {
            $usuarioModel = Usuario::where(
                'id_usuario',
                $usuarioModel->id_usuario
            )->lockForUpdate()->firstOrFail();

            $asociacion = DB::table('restaurantes_usuarios')
                ->where('id_restaurante', $restauranteModel->id_restaurante)
                ->where('id_usuario', $usuarioModel->id_usuario)
                ->lockForUpdate()
                ->first();

            if (! $asociacion) {
                abort(404, 'El usuario no pertenece a este restaurante.');
            }

            $nuevoRol = $datos['rol'] ?? $asociacion->rol;

            $nuevoRol = $datos['rol'] ?? $asociacion->rol;

            if ($asociacion->rol === 'administrador'
                && $usuarioModel->estado === 'activo'
                && $nuevoRol !== 'administrador') {

                $administradoresActivos = DB::table('restaurantes_usuarios')
                    ->join(
                        'usuarios',
                        'usuarios.id_usuario',
                        '=',
                        'restaurantes_usuarios.id_usuario'
                    )
                    ->where(
                        'restaurantes_usuarios.id_restaurante',
                        $restauranteModel->id_restaurante
                    )
                    ->where('restaurantes_usuarios.rol', 'administrador')
                    ->where('usuarios.estado', 'activo')
                    ->count();

                if ($administradoresActivos <= 1) {
                    throw ValidationException::withMessages([
                        'rol' => [
                            'No puedes quitar el rol al último administrador activo del restaurante.',
                        ],
                    ]);
                }
            }

            // No permitir desactivar la propia cuenta desde este endpoint.
            if (
                (int) $request->user()->id_usuario === (int) $usuarioModel->id_usuario
                && $nuevoEstado !== 'activo'
            ) {
                throw ValidationException::withMessages([
                    'estado' => ['No puedes desactivar tu propia cuenta.'],
                ]);
            }

            $camposUsuario = array_intersect_key(
                $datos,
                array_flip(['nombre', 'correo', 'estado'])
            );

            $usuarioModel->fill($camposUsuario);
            $usuarioModel->save();

            if (isset($datos['rol'])) {
                DB::table('restaurantes_usuarios')
                    ->where(
                        'id_restaurante',
                        $restauranteModel->id_restaurante
                    )
                    ->where('id_usuario', $usuarioModel->id_usuario)
                    ->update(['rol' => $datos['rol']]);
            }
        });

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente.',
        ]);
    }

    /**
     * Desvincular un usuario del restaurante.
     */
    public function destroy(string $restaurante, string $usuario): JsonResponse
    {
        $restauranteModel = $this->restaurante($restaurante);
        $usuarioModel     = $this->usuarioDelRestaurante($restauranteModel, $usuario);

        if ((int) request()->user()->id_usuario === (int) $usuarioModel->id_usuario) {
            throw ValidationException::withMessages([
                'usuario' => ['No puedes desvincularte a ti mismo.'],
            ]);
        }

        DB::transaction(function () use ($restauranteModel, $usuarioModel) {
            $asociacion = DB::table('restaurantes_usuarios')
                ->where('id_restaurante', $restauranteModel->id_restaurante)
                ->where('id_usuario', $usuarioModel->id_usuario)
                ->lockForUpdate()
                ->first();

            if (! $asociacion) {
                abort(404, 'El usuario no pertenece a este restaurante.');
            }

            if ($asociacion->rol === 'administrador') {
                $administradoresActivos = DB::table('restaurantes_usuarios')
                    ->join(
                        'usuarios',
                        'usuarios.id_usuario',
                        '=',
                        'restaurantes_usuarios.id_usuario'
                    )
                    ->where(
                        'restaurantes_usuarios.id_restaurante',
                        $restauranteModel->id_restaurante
                    )
                    ->where('restaurantes_usuarios.rol', 'administrador')
                    ->where('usuarios.estado', 'activo')
                    ->count();

                if (
                    $administradoresActivos <= 1
                    && $usuarioModel->estado === 'activo'
                ) {
                    throw ValidationException::withMessages([
                        'usuario' => [
                            'No puedes desvincular al último administrador activo del restaurante.',
                        ],
                    ]);
                }
            }

            $restauranteModel->usuarios()->detach($usuarioModel->id_usuario);

            // El usuario global se conserva, ya que podría pertenecer
            // también a otros restaurantes.
        });

        return response()->json([
            'mensaje' => 'Usuario desvinculado del restaurante correctamente.',
        ]);
    }

    private function restaurante(string $id): Restaurante
    {
        return Restaurante::query()
            ->where('id_restaurante', $id)
            ->where('estado', 'activo')
            ->whereHas('usuarios', function ($query) {
                $query->where('usuarios.id_usuario', request()->user()->id_usuario);
            })
            ->firstOrFail();
    }

    private function usuarioDelRestaurante(
        Restaurante $restaurante,
        string $idUsuario
    ): Usuario {
        return $restaurante->usuarios()
            ->where('usuarios.id_usuario', $idUsuario)
            ->firstOrFail();
    }
}
