<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRestaurante(
        string $rol = 'administrador'
    ): array {
        $usuario = Usuario::create([
            'nombre' => 'Usuario prueba',
            'correo' => uniqid('categoria_') . '@example.com',
            'contrasena' => Hash::make('Password123!'),
            'estado' => 'activo',
        ]);

        $restaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Restaurante prueba ' . uniqid(),
            'slug' => uniqid('restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        DB::table('restaurantes_usuarios')->insert([
            'id_usuario' => $usuario->id_usuario,
            'id_restaurante' => $restaurante,
            'rol' => $rol,
            
        ]);

        $token = $usuario->createToken('prueba-categoria')->plainTextToken;

        return [$usuario, $restaurante, $token];
    }

    public function test_administrador_puede_crear_categoria(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/categorias", [
                'nombre' => 'Bebidas',
                'descripcion' => 'Bebidas frías y calientes',
                'orden_menu' => 1,
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('categoria.nombre', 'Bebidas');

        $this->assertDatabaseHas('categorias', [
            'id_restaurante' => $restaurante,
            'nombre' => 'Bebidas',
        ]);
    }

    public function test_no_administrador_no_puede_crear_categoria(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante('empleado');

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/categorias", [
                'nombre' => 'Bebidas',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('categorias', [
            'id_restaurante' => $restaurante,
            'nombre' => 'Bebidas',
        ]);
    }

    public function test_usuario_no_puede_acceder_a_otro_restaurante(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$otroRestaurante}/categorias")
            ->assertForbidden();
    }

    public function test_categoria_no_se_puede_consultar_desde_otro_restaurante(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        $categoria = DB::table('categorias')->insertGetId([
            'id_restaurante' => $restaurante,
            'nombre' => 'Postres',
            'descripcion' => null,
            'orden_menu' => 0,
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_categoria');

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $this->withToken($token)
            ->getJson(
                "/api/restaurantes/{$otroRestaurante}/categorias/{$categoria}"
            )
            ->assertForbidden();
    }

    public function test_no_permite_crear_categoria_sin_nombre(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/categorias", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_no_permite_nombres_duplicados_en_el_mismo_restaurante(): void
    {
        [, $restaurante, $token] =
            $this->crearUsuarioConRestaurante();

        DB::table('categorias')->insert([
            'id_restaurante' => $restaurante,
            'nombre' => 'Bebidas',
            'descripcion' => null,
            'orden_menu' => 0,
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/categorias", [
                'nombre' => 'Bebidas',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }
}