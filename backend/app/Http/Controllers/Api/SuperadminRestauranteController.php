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

class SuperadminRestauranteController extends Controller
{
    /**
     * Lista todos los restaurantes para el panel global.
     */

    public function index(): JsonResponse
    {
        // Seleccionar un administrador activo por restaurante.
        $administradores = DB::table('restaurantes_usuarios as ru')
            ->join('usuarios as u', 'u.id_usuario', '=', 'ru.id_usuario')
            ->where('ru.rol', 'administrador')
            ->where('u.estado', 'activo')
            ->select([
                'ru.id_restaurante',
                DB::raw('MIN(ru.id_usuario) as id_usuario'),
            ])
            ->groupBy('ru.id_restaurante');

        $restaurantes = Restaurante::query()
            ->leftJoinSub(
                $administradores,
                'administradores',
                function ($join) {
                    $join->on(
                        'administradores.id_restaurante',
                        '=',
                        'restaurantes.id_restaurante'
                    );
                }
            )
            ->leftJoin(
                'usuarios as u',
                'u.id_usuario',
                '=',
                'administradores.id_usuario'
            )
            ->select([
                'restaurantes.id_restaurante as id',
                'restaurantes.nombre',
                'restaurantes.slug',
                'u.nombre as administrador',
                'u.correo as correo',
                'restaurantes.estado',
                'restaurantes.creado_en as fecha',
            ])
            ->orderByDesc('restaurantes.id_restaurante')
            ->get()
            ->map(function ($restaurante) {
                return [
                    'id'            => (int) $restaurante->id,
                    'nombre'        => $restaurante->nombre,
                    'slug'          => $restaurante->slug,
                    'administrador' => $restaurante->administrador,
                    'correo'        => $restaurante->correo,
                    'estado'        => $restaurante->estado,
                    'fecha'         => $restaurante->fecha
                        ? substr((string) $restaurante->fecha, 0, 10)
                        : null,
                ];
            });

        // Contar administradores activos sin duplicar usuarios.
        $totalAdministradores = DB::table('restaurantes_usuarios as ru')
            ->join('usuarios as u', 'u.id_usuario', '=', 'ru.id_usuario')
            ->where('ru.rol', 'administrador')
            ->where('u.estado', 'activo')
            ->distinct('ru.id_usuario')
            ->count('ru.id_usuario');

        return response()->json([
            'restaurantes'          => $restaurantes,
            'total_administradores' => $totalAdministradores,
        ]);
    }

    /**
     * Crea un restaurante y su primer administrador.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'               => ['required', 'string', 'max:255'],
            'slug'                 => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('restaurantes', 'slug'),
            ],
            'descripcion'          => ['nullable', 'string'],
            'telefono'             => ['nullable', 'string', 'max:50'],
            'correo'               => ['nullable', 'email', 'max:255'],
            'direccion'            => ['nullable', 'string', 'max:255'],

            'nombre_administrador' => [
                'required',
                'string',
                'max:255',
            ],
            'correo_administrador' => [
                'required',
                'email',
                'max:255',
                Rule::unique('usuarios', 'correo'),
            ],
            'contrasena'           => [
                'required',
                'string',
                'min:8',
            ],
        ]);

        $resultado = DB::transaction(function () use ($datos) {
            $restaurante = Restaurante::create([
                'nombre'      => $datos['nombre'],
                'slug'        => $datos['slug'],
                'descripcion' => $datos['descripcion'] ?? null,
                'telefono'    => $datos['telefono'] ?? null,
                'correo'      => $datos['correo'] ?? null,
                'direccion'   => $datos['direccion'] ?? null,
                'estado'      => 'activo',
            ]);

            $administrador = Usuario::create([
                'nombre'     => $datos['nombre_administrador'],
                'correo'     => $datos['correo_administrador'],
                'contrasena' => Hash::make($datos['contrasena']),
                'estado'     => 'activo',
            ]);

            $restaurante->usuarios()->attach(
                $administrador->id_usuario,
                [
                    'rol'       => 'administrador',
                    'creado_en' => now(),
                ]
            );

            return [
                'id'            => $restaurante->id_restaurante,
                'nombre'        => $restaurante->nombre,
                'slug'          => $restaurante->slug,
                'administrador' => $administrador->nombre,
                'correo'        => $administrador->correo,
                'estado'        => $restaurante->estado,
                'fecha'         => $restaurante->creado_en
                    ? substr((string) $restaurante->creado_en, 0, 10)
                    : now()->toDateString(),
            ];
        });

        return response()->json([
            'mensaje'     => 'Restaurante creado correctamente.',
            'restaurante' => $resultado,
        ], 201);
    }

    /**
     * Consulta los datos de un restaurante para editarlo.
     */
    public function show(int $id): JsonResponse
    {
        $restaurante = Restaurante::find($id);

        if (! $restaurante) {
            return response()->json([
                'mensaje' => 'El restaurante no existe.',
            ], 404);
        }

        return response()->json([
            'restaurante' => [
                'id'          => (int) $restaurante->id_restaurante,
                'nombre'      => $restaurante->nombre,
                'slug'        => $restaurante->slug,
                'descripcion' => $restaurante->descripcion,
                'telefono'    => $restaurante->telefono,
                'correo'      => $restaurante->correo,
                'direccion'   => $restaurante->direccion,
                'estado'      => $restaurante->estado,
            ],
        ]);
    }

    /**
     * Actualiza los datos generales de un restaurante.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $restaurante = Restaurante::find($id);

        if (! $restaurante) {
            return response()->json([
                'mensaje' => 'El restaurante no existe.',
            ], 404);
        }

        $datos = $request->validate([
            'nombre'      => [
                'required',
                'string',
                'max:255',
            ],
            'slug'        => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('restaurantes', 'slug')
                    ->ignore($id, 'id_restaurante'),
            ],
            'descripcion' => [
                'nullable',
                'string',
            ],
            'telefono'    => [
                'nullable',
                'string',
                'max:50',
            ],
            'correo'      => [
                'nullable',
                'email',
                'max:255',
            ],
            'direccion'   => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $restaurante->nombre      = $datos['nombre'];
        $restaurante->slug        = $datos['slug'];
        $restaurante->descripcion = $datos['descripcion'] ?? null;
        $restaurante->telefono    = $datos['telefono'] ?? null;
        $restaurante->correo      = $datos['correo'] ?? null;
        $restaurante->direccion   = $datos['direccion'] ?? null;

        $restaurante->save();

        return response()->json([
            'mensaje'     => 'Restaurante actualizado correctamente.',
            'restaurante' => [
                'id'          => (int) $restaurante->id_restaurante,
                'nombre'      => $restaurante->nombre,
                'slug'        => $restaurante->slug,
                'descripcion' => $restaurante->descripcion,
                'telefono'    => $restaurante->telefono,
                'correo'      => $restaurante->correo,
                'direccion'   => $restaurante->direccion,
                'estado'      => $restaurante->estado,
            ],
        ]);
    }

    /**
     * Suspende o reactiva un restaurante.
     */
    public function cambiarEstado(
        Request $request,
        int $id
    ): JsonResponse {
        $datos = $request->validate([
            'estado' => [
                'required',
                'string',
                Rule::in(['activo', 'suspendido']),
            ],
        ]);

        $restaurante = Restaurante::find($id);

        if (! $restaurante) {
            return response()->json([
                'mensaje' => 'El restaurante no existe.',
            ], 404);
        }

        $restaurante->estado = $datos['estado'];
        $restaurante->save();

        return response()->json([
            'mensaje'     => $restaurante->estado === 'activo'
                ? 'Restaurante reactivado correctamente.'
                : 'Restaurante suspendido correctamente.',
            'restaurante' => [
                'id'     => (int) $restaurante->id_restaurante,
                'nombre' => $restaurante->nombre,
                'estado' => $restaurante->estado,
            ],
        ]);
    }
}
