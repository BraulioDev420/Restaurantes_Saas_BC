<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    private function prepararRestaurante(string $rol = 'administrador'): array
    {
        $usuario = Usuario::create([
            'nombre' => 'Usuario prueba',
            'correo' => uniqid('cliente_') . '@example.com',
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

        $token = $usuario->createToken('prueba-cliente')->plainTextToken;

        return [$restaurante, $token];
    }

    public function test_usuario_autorizado_puede_crear_cliente(): void
    {
        [$restaurante, $token] = $this->prepararRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/clientes", [
                'nombre' => 'Juan Pérez',
                'telefono' => '3001234567',
                'correo' => 'juan@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('cliente.nombre', 'Juan Pérez');

        $this->assertDatabaseHas('clientes', [
            'id_restaurante' => $restaurante,
            'telefono' => '3001234567',
        ]);
    }

    public function test_no_permite_crear_cliente_sin_nombre(): void
    {
        [$restaurante, $token] = $this->prepararRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/clientes", [
                'telefono' => '3001234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_no_permite_telefonos_duplicados_en_el_mismo_restaurante(): void
    {
        [$restaurante, $token] = $this->prepararRestaurante();

        DB::table('clientes')->insert([
            'id_restaurante' => $restaurante,
            'nombre' => 'Cliente existente',
            'telefono' => '3001234567',
            'correo' => null,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/clientes", [
                'nombre' => 'Otro cliente',
                'telefono' => '3001234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefono']);
    }

    public function test_no_permite_correo_invalido(): void
    {
        [$restaurante, $token] = $this->prepararRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/clientes", [
                'nombre' => 'Juan Pérez',
                'telefono' => '3001234567',
                'correo' => 'correo-invalido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo']);
    }

    public function test_usuario_no_puede_acceder_a_otro_restaurante(): void
    {
        [, $token] = $this->prepararRestaurante();

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$otroRestaurante}/clientes")
            ->assertForbidden();
    }

    public function test_no_administrador_puede_crear_cliente(): void
    {
        [$restaurante, $token] = $this->prepararRestaurante('empleado');

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/clientes", [
                'nombre' => 'Cliente de prueba',
                'telefono' => '3009876543',
            ])
            ->assertCreated();
    }
}