<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Restaurante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GastoTest extends TestCase
{
    use RefreshDatabase;

    private function crearDatosDePrueba(string $rol = 'administrador'): array
    {
        $restaurante = Restaurante::create([
            'nombre' => 'Restaurante Gastos Test',
            'slug' => 'restaurante-gastos-test',
            'estado' => 'activo',
        ]);

        $usuario = Usuario::create([
            'nombre' => 'Usuario Gastos Test',
            'correo' => uniqid('gastos_') . '@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado' => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_usuario' => $usuario->id_usuario,
            'rol' => $rol,
        ]);

        return [$restaurante, $usuario];
    }

    private function datosGasto(array $sobrescribir = []): array
    {
        return array_merge([
            'descripcion' => 'Compra de ingredientes',
            'categoria' => 'Insumos',
            'monto' => '25000.00',
            'fecha_gasto' => '2026-10-20',
            'comprobante_url' => null,
        ], $sobrescribir);
    }

    private function crearGasto(
        Restaurante $restaurante,
        Usuario $usuario,
        array $datos = []
    ): Gasto {
        return Gasto::create(array_merge(
            $this->datosGasto($datos),
            [
                'id_restaurante' => $restaurante->id_restaurante,
                'id_creado_por' => $usuario->id_usuario,
            ]
        ));
    }

    public function test_administrador_puede_registrar_un_gasto(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
                $this->datosGasto()
            );

        $respuesta
            ->assertCreated()
            ->assertJsonPath('gasto.descripcion', 'Compra de ingredientes')
            ->assertJsonPath('gasto.categoria', 'Insumos')
            ->assertJsonPath('gasto.monto', '25000.00')
            ->assertJsonPath('gasto.id_creado_por', $usuario->id_usuario);

        $this->assertDatabaseHas('gastos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_creado_por' => $usuario->id_usuario,
            'descripcion' => 'Compra de ingredientes',
            'monto' => '25000.00',
        ]);
    }

    public function test_no_permite_registrar_gastos_con_datos_invalidos(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $casosInvalidos = [
            ['descripcion' => ''],
            ['categoria' => ''],
            ['monto' => 0],
            ['monto' => -100],
            ['monto' => 'abc'],
            ['monto' => '100.999'],
            ['fecha_gasto' => 'fecha-invalida'],
            ['comprobante_url' => 'no-es-una-url'],
        ];

        foreach ($casosInvalidos as $cambios) {
            $respuesta = $this->actingAs($usuario, 'sanctum')
                ->postJson(
                    "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
                    $this->datosGasto($cambios)
                );

            $respuesta->assertUnprocessable();
        }

        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_usuario_no_autenticado_no_puede_registrar_gastos(): void
    {
        [$restaurante] = $this->crearDatosDePrueba();

        $this->postJson(
            "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
            $this->datosGasto()
        )->assertUnauthorized();

        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_puede_listar_y_consultar_gastos_del_restaurante(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $this->actingAs($usuario, 'sanctum')
            ->getJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos"
            )
            ->assertOk()
            ->assertJsonPath('data.0.id_gasto', $gasto->id_gasto);

        $this->actingAs($usuario, 'sanctum')
            ->getJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos/{$gasto->id_gasto}"
            )
            ->assertOk()
            ->assertJsonPath('gasto.id_gasto', $gasto->id_gasto);
    }

    public function test_puede_actualizar_un_gasto(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos/{$gasto->id_gasto}",
                [
                    'descripcion' => 'Compra actualizada',
                    'monto' => '30000.00',
                ]
            )
            ->assertOk()
            ->assertJsonPath('gasto.descripcion', 'Compra actualizada')
            ->assertJsonPath('gasto.monto', '30000.00');

        $this->assertDatabaseHas('gastos', [
            'id_gasto' => $gasto->id_gasto,
            'descripcion' => 'Compra actualizada',
            'monto' => '30000.00',
        ]);
    }

    public function test_no_permite_actualizar_un_gasto_con_datos_invalidos(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos/{$gasto->id_gasto}",
                ['monto' => -1]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('gastos', [
            'id_gasto' => $gasto->id_gasto,
            'monto' => '25000.00',
        ]);
    }

    public function test_solo_el_administrador_puede_eliminar_gastos(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba('administrador');

        $gasto = $this->crearGasto($restaurante, $usuario);

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos/{$gasto->id_gasto}"
            )
            ->assertOk();

        $this->assertDatabaseMissing('gastos', [
            'id_gasto' => $gasto->id_gasto,
        ]);
    }

    public function test_empleado_puede_consultar_pero_no_registrar_gastos(): void
    {
        [$restaurante, $empleado] = $this->crearDatosDePrueba('empleado');

        $this->actingAs($empleado, 'sanctum')
            ->getJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos"
            )
            ->assertOk();

        $this->actingAs($empleado, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
                $this->datosGasto()
            )
            ->assertForbidden();

        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_cajero_puede_registrar_gastos(): void
    {
        [$restaurante, $cajero] = $this->crearDatosDePrueba('cajero');

        $this->actingAs($cajero, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
                $this->datosGasto()
            )
            ->assertCreated();

        $this->assertDatabaseCount('gastos', 1);
    }

    public function test_usuario_de_otro_restaurante_no_puede_consultar_gastos(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $otroRestaurante = Restaurante::create([
            'nombre' => 'Otro Restaurante Gastos',
            'slug' => 'otro-restaurante-gastos',
            'estado' => 'activo',
        ]);

        $otroUsuario = Usuario::create([
            'nombre' => 'Otro Administrador',
            'correo' => uniqid('otro_') . '@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado' => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario' => $otroUsuario->id_usuario,
            'rol' => 'administrador',
        ]);

        $this->actingAs($otroUsuario, 'sanctum')
            ->getJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos/{$gasto->id_gasto}"
            )
            ->assertForbidden();
    }

    public function test_no_permite_modificar_gastos_de_otro_restaurante(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $otroRestaurante = Restaurante::create([
            'nombre' => 'Restaurante Ajeno',
            'slug' => 'restaurante-ajeno',
            'estado' => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario' => $usuario->id_usuario,
            'rol' => 'administrador',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$otroRestaurante->id_restaurante}/gastos/{$gasto->id_gasto}",
                ['descripcion' => 'Intento de modificación cruzada']
            )
            ->assertNotFound();

        $this->assertDatabaseHas('gastos', [
            'id_gasto' => $gasto->id_gasto,
            'id_restaurante' => $restaurante->id_restaurante,
            'descripcion' => 'Compra de ingredientes',
        ]);
    }

    public function test_no_permite_asignar_un_creador_arbitrario(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/gastos",
                $this->datosGasto([
                    'id_creado_por' => 999999,
                ])
            );

        $respuesta->assertCreated();

        $this->assertDatabaseHas('gastos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_creado_por' => $usuario->id_usuario,
            'descripcion' => 'Compra de ingredientes',
        ]);

        $this->assertDatabaseMissing('gastos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_creado_por' => 999999,
        ]);
    }

    public function test_administrador_no_puede_eliminar_gasto_de_otro_restaurante(): void
    {
        [$restaurante, $usuario] = $this->crearDatosDePrueba();

        $gasto = $this->crearGasto($restaurante, $usuario);

        $otroRestaurante = Restaurante::create([
            'nombre' => 'Restaurante Externo',
            'slug' => 'restaurante-externo',
            'estado' => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario' => $usuario->id_usuario,
            'rol' => 'administrador',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson(
                "/api/restaurantes/{$otroRestaurante->id_restaurante}/gastos/{$gasto->id_gasto}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('gastos', [
            'id_gasto' => $gasto->id_gasto,
        ]);
    }
}
