<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRestaurante(
        string $rol = 'administrador'
    ): array {
        $usuario = Usuario::create([
            'nombre' => 'Administrador de prueba',
            'correo' => uniqid('admin_') . '@example.com',
            'contrasena' => Hash::make('Password123!'),
            'estado' => 'activo',
        ]);

        $restaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Restaurante de prueba ' . uniqid(),
            'slug' => uniqid('restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        DB::table('restaurantes_usuarios')->insert([
            'id_usuario' => $usuario->id_usuario,
            'id_restaurante' => $restaurante,
            'rol' => $rol,
            'creado_en' => now(),
        ]);

        $token = $usuario->createToken('prueba-usuarios')
            ->plainTextToken;

        return [$usuario, $restaurante, $token];
    }

    public function test_administrador_puede_listar_usuarios_del_restaurante(): void
    {
        [, $restaurante, $token] = $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$restaurante}/usuarios")
            ->assertOk()
            ->assertJsonStructure([
                'usuarios' => [
                    '*' => [
                        'id_usuario',
                        'nombre',
                        'correo',
                        'estado',
                        'rol',
                        'creado_en',
                    ],
                ],
            ]);
    }

    public function test_administrador_puede_crear_usuario_con_contrasena_cifrada(): void
    {
        [, $restaurante, $token] = $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/usuarios", [
                'nombre' => 'Empleado de prueba',
                'correo' => 'empleado_' . uniqid() . '@example.com',
                'contrasena' => 'Password123!',
                'rol' => 'empleado',
            ])
            ->assertCreated()
            ->assertJsonPath('usuario.rol', 'empleado')
            ->assertJsonMissingPath('usuario.contrasena');

        $usuario = Usuario::query()
            ->where('nombre', 'Empleado de prueba')
            ->firstOrFail();

        $this->assertTrue(
            Hash::check('Password123!', $usuario->contrasena)
        );

        $this->assertDatabaseHas('restaurantes_usuarios', [
            'id_restaurante' => $restaurante,
            'id_usuario' => $usuario->id_usuario,
            'rol' => 'empleado',
        ]);
    }

    public function test_empleado_no_puede_administrar_usuarios(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante('empleado');

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$restaurante}/usuarios")
            ->assertForbidden();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/usuarios", [
                'nombre' => 'Nuevo usuario',
                'correo' => 'nuevo_' . uniqid() . '@example.com',
                'contrasena' => 'Password123!',
                'rol' => 'cajero',
            ])
            ->assertForbidden();
    }

    public function test_usuario_no_puede_listar_usuarios_de_otro_restaurante(): void
    {
        [, , $token] = $this->crearUsuarioConRestaurante();

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$otroRestaurante}/usuarios")
            ->assertForbidden();
    }

    public function test_no_permite_crear_usuario_con_rol_invalido(): void
    {
        [, $restaurante, $token] = $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/usuarios", [
                'nombre' => 'Usuario inválido',
                'correo' => 'invalido_' . uniqid() . '@example.com',
                'contrasena' => 'Password123!',
                'rol' => 'superadmin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol']);
    }

    public function test_no_permite_quitar_el_rol_al_ultimo_administrador(): void
    {
        [$administrador, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->patchJson(
                "/api/restaurantes/{$restaurante}/usuarios/{$administrador->id_usuario}",
                ['rol' => 'empleado']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol']);

        $this->assertDatabaseHas('restaurantes_usuarios', [
            'id_restaurante' => $restaurante,
            'id_usuario' => $administrador->id_usuario,
            'rol' => 'administrador',
        ]);
    }


public function test_desvincular_usuario_no_elimina_su_cuenta_ni_otras_asociaciones(): void
{
    [, $restauranteA, $token] = $this->crearUsuarioConRestaurante();

    $restauranteB = DB::table('restaurantes')->insertGetId([
        'nombre' => 'Segundo restaurante ' . uniqid(),
        'slug' => uniqid('segundo-restaurante-'),
        'estado' => 'activo',
        'creado_en' => now(),
        'actualizado_en' => now(),
    ], 'id_restaurante');

    $usuarioCompartido = Usuario::create([
        'nombre' => 'Usuario compartido',
        'correo' => uniqid('compartido_') . '@example.com',
        'contrasena' => Hash::make('Password123!'),
        'estado' => 'activo',
    ]);

    DB::table('restaurantes_usuarios')->insert([
        [
            'id_usuario' => $usuarioCompartido->id_usuario,
            'id_restaurante' => $restauranteA,
            'rol' => 'empleado',
            'creado_en' => now(),
        ],
        [
            'id_usuario' => $usuarioCompartido->id_usuario,
            'id_restaurante' => $restauranteB,
            'rol' => 'cajero',
            'creado_en' => now(),
        ],
    ]);

    $this->withToken($token)
        ->deleteJson(
            "/api/restaurantes/{$restauranteA}/usuarios/{$usuarioCompartido->id_usuario}"
        )
        ->assertOk();

    $this->assertDatabaseHas('usuarios', [
        'id_usuario' => $usuarioCompartido->id_usuario,
        'estado' => 'activo',
    ]);

    $this->assertDatabaseMissing('restaurantes_usuarios', [
        'id_restaurante' => $restauranteA,
        'id_usuario' => $usuarioCompartido->id_usuario,
    ]);

    $this->assertDatabaseHas('restaurantes_usuarios', [
        'id_restaurante' => $restauranteB,
        'id_usuario' => $usuarioCompartido->id_usuario,
        'rol' => 'cajero',
    ]);
}


















}