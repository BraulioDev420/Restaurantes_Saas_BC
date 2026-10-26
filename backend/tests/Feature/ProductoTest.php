<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use RefreshDatabase;

    private function prepararRestaurante(): array
    {
        $usuario = Usuario::create([
            'nombre' => 'Usuario prueba',
            'correo' => uniqid('producto_') . '@example.com',
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
            'rol' => 'administrador',
        ]);

        $token = $usuario->createToken('prueba-producto')->plainTextToken;

        $categoria = DB::table('categorias')->insertGetId([
            'id_restaurante' => $restaurante,
            'nombre' => 'Bebidas',
            'descripcion' => null,
            'orden_menu' => 0,
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_categoria');

        return [$restaurante, $categoria, $token];
    }

    public function test_administrador_puede_crear_producto(): void
    {
        [$restaurante, $categoria, $token] = $this->prepararRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/productos", [
                'id_categoria' => $categoria,
                'nombre' => 'Agua',
                'precio' => 3000,
                'disponible' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('producto.nombre', 'Agua');

        $this->assertDatabaseHas('productos', [
            'id_restaurante' => $restaurante,
            'nombre' => 'Agua',
        ]);
    }

    public function test_no_permite_precio_negativo(): void
    {
        [$restaurante, $categoria, $token] = $this->prepararRestaurante();

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/productos", [
                'id_categoria' => $categoria,
                'nombre' => 'Agua',
                'precio' => -100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio']);
    }

    public function test_no_permite_categoria_de_otro_restaurante(): void
    {
        [$restaurante, , $token] = $this->prepararRestaurante();

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $otraCategoria = DB::table('categorias')->insertGetId([
            'id_restaurante' => $otroRestaurante,
            'nombre' => 'Postres',
            'descripcion' => null,
            'orden_menu' => 0,
            'activo' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_categoria');

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/productos", [
                'id_categoria' => $otraCategoria,
                'nombre' => 'Producto ajeno',
                'precio' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);
    }

    public function test_no_permite_categoria_inactiva(): void
    {
        [$restaurante, $categoria, $token] = $this->prepararRestaurante();

        DB::table('categorias')
            ->where('id_categoria', $categoria)
            ->update(['activo' => false]);

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/productos", [
                'id_categoria' => $categoria,
                'nombre' => 'Producto prueba',
                'precio' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_categoria']);
    }

    public function test_producto_no_se_consulta_desde_otro_restaurante(): void
    {
        [$restaurante, $categoria, $token] = $this->prepararRestaurante();

        $producto = DB::table('productos')->insertGetId([
            'id_restaurante' => $restaurante,
            'id_categoria' => $categoria,
            'nombre' => 'Agua',
            'descripcion' => null,
            'precio' => 3000,
            'imagen_url' => null,
            'disponible' => true,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_producto');

        $otroRestaurante = DB::table('restaurantes')->insertGetId([
            'nombre' => 'Otro restaurante ' . uniqid(),
            'slug' => uniqid('otro-restaurante-'),
            'estado' => 'activo',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ], 'id_restaurante');

        $this->withToken($token)
            ->getJson("/api/restaurantes/{$otroRestaurante}/productos/{$producto}")
            ->assertForbidden();
    }

    public function test_no_administrador_no_puede_crear_producto(): void
    {
        [$restaurante, $categoria, $token] = $this->prepararRestaurante();

        // Cambiar el rol de la asociación del usuario.
        DB::table('restaurantes_usuarios')
            ->where('id_restaurante', $restaurante)
            ->update(['rol' => 'empleado']);

        $this->withToken($token)
            ->postJson("/api/restaurantes/{$restaurante}/productos", [
                'id_categoria' => $categoria,
                'nombre' => 'Agua',
                'precio' => 3000,
            ])
            ->assertForbidden();
    }
}