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
        $restaurantes = Restaurante::query()
            ->leftJoin(
                'restaurantes_usuarios as ru',
                function ($join) {
                    $join->on(
                        'ru.id_restaurante',
                        '=',
                        'restaurantes.id_restaurante'
                    )->where('ru.rol', '=', 'administrador');
                }
            )
            ->leftJoin(
                'usuarios as u',
                'u.id_usuario',
                '=',
                'ru.id_usuario'
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

        return response()->json([
            'restaurantes' => $restaurantes,
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
