<?php
namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Restaurante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepara un restaurante, un administrador y un pedido
     * para utilizar en las pruebas.
     */
    private function crearDatosDePrueba(): array
    {
        $restaurante = Restaurante::create([
            'nombre' => 'Restaurante Test',
            'slug'   => 'restaurante-test',
            'estado' => 'activo',
        ]);

        $usuario = Usuario::create([
            'nombre'     => 'Administrador Test',
            'correo'     => 'admin.test@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado'     => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_usuario'     => $usuario->id_usuario,
            'rol'            => 'administrador',
        ]);

        $pedido = Pedido::create([
            'id_restaurante'   => $restaurante->id_restaurante,
            'numero_pedido'    => 'PED-TEST-001',
            'nombre_cliente'   => 'Cliente Test',
            'telefono_cliente' => '3001234567',
            'tipo_entrega'     => 'recoger',
            'subtotal'         => 15000,
            'costo_domicilio'  => 0,
            'total'            => 15000,
            'estado'           => 'pendiente',
        ]);

        return [$restaurante, $usuario, $pedido];
    }

    public function test_usuario_autenticado_puede_registrar_un_pago_pendiente(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'     => $pedido->id_pedido,
                    'metodo_pago'   => 'transferencia',
                    'monto'         => 5000,
                    'observaciones' => 'Pago de prueba automatizada',
                ]
            );

        $respuesta
            ->assertCreated()
            ->assertJsonPath('pago.estado', 'pendiente')
            ->assertJsonPath('pago.monto', '5000.00');

        $this->assertDatabaseHas('pagos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'monto'          => 5000,
            'estado'         => 'pendiente',
        ]);
    }

    public function test_puede_registrar_pago_con_comprobante_privado(): void
    {
        Storage::fake('local');

        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->post(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 5000,
                    'comprobante' => UploadedFile::fake()->create(
                        'comprobante.pdf',
                        100,
                        'application/pdf'
                    ),
                ],
                ['Accept' => 'application/json']
            );

        $respuesta->assertCreated();

        $ruta = $respuesta->json('pago.comprobante_url');

        $this->assertNotEmpty($ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->assertDatabaseHas('pagos', [
            'id_pago'         => $respuesta->json('pago.id_pago'),
            'comprobante_url' => $ruta,
        ]);
    }

    public function test_no_permite_registrar_un_pago_superior_al_total_del_pedido(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'     => $pedido->id_pedido,
                    'metodo_pago'   => 'transferencia',
                    'monto'         => 16000,
                    'observaciones' => 'Prueba de sobrepago',
                ]
            );

        $respuesta->assertStatus(422);

        $this->assertDatabaseMissing('pagos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'monto'          => 16000,
        ]);
    }

    public function test_no_permite_superar_el_saldo_restante_con_un_pago_pendiente(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        // Registrar primero un abono pendiente de $10.000.
        $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 10000,
                ]
            )
            ->assertCreated();

        // Intentar registrar otros $6.000: excedería el pedido de $15.000.
        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 6000,
                ]
            );

        $respuesta->assertStatus(422);

        // Solo debe existir el primer abono de $10.000.
        $this->assertDatabaseCount('pagos', 1);

        $this->assertDatabaseHas('pagos', [
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'monto'          => 10000,
            'estado'         => 'pendiente',
        ]);
    }

    public function test_no_permite_registrar_pagos_con_montos_invalidos(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $montosInvalidos = [0, -1000, 1000.999];

        foreach ($montosInvalidos as $monto) {
            $respuesta = $this->actingAs($usuario, 'sanctum')
                ->postJson(
                    "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                    [
                        'id_pedido'   => $pedido->id_pedido,
                        'metodo_pago' => 'transferencia',
                        'monto'       => $monto,
                    ]
                );

            $respuesta->assertStatus(422);
        }

        // Ninguno de los montos inválidos debe guardarse.
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_usuario_no_autenticado_no_puede_registrar_un_pago(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        // No utilizamos actingAs(): la petición se hace sin autenticación.
        $respuesta = $this->postJson(
            "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
            [
                'id_pedido'   => $pedido->id_pedido,
                'metodo_pago' => 'transferencia',
                'monto'       => 5000,
            ]
        );

        $respuesta->assertUnauthorized();

        // No debe haberse creado ningún pago.
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_administrador_puede_verificar_un_pago_pendiente(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        // Crear un pago pendiente.
        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
            'observaciones'  => 'Pago por verificar',
        ]);

        // El administrador confirma el pago.
        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                [
                    'estado'        => 'verificado',
                    'observaciones' => 'Transferencia confirmada',
                ]
            );

        $respuesta
            ->assertOk()
            ->assertJsonPath('pago.estado', 'verificado')
            ->assertJsonPath('pago.id_verificado_por', $usuario->id_usuario)
            ->assertJsonPath('pago.observaciones', 'Transferencia confirmada');

        $this->assertDatabaseHas('pagos', [
            'id_pago'           => $pago->id_pago,
            'estado'            => 'verificado',
            'id_verificado_por' => $usuario->id_usuario,
            'observaciones'     => 'Transferencia confirmada',
        ]);
    }

    public function test_no_permite_cambiar_el_estado_de_un_pago_ya_verificado(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
            'observaciones'  => 'Pago de prueba',
        ]);

        // Verificar el pago por primera vez.
        $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                ['estado' => 'verificado']
            )
            ->assertOk();

        // Intentar rechazar el mismo pago después de verificarlo.
        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                ['estado' => 'rechazado']
            );

        $respuesta->assertStatus(422);

        // El estado original debe mantenerse.
        $this->assertDatabaseHas('pagos', [
            'id_pago' => $pago->id_pago,
            'estado'  => 'verificado',
        ]);
    }

    public function test_administrador_puede_rechazar_un_pago_pendiente(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
            'observaciones'  => 'Pago por revisar',
        ]);

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                [
                    'estado'        => 'rechazado',
                    'observaciones' => 'El comprobante no coincide',
                ]
            );

        $respuesta
            ->assertOk()
            ->assertJsonPath('pago.estado', 'rechazado')
            ->assertJsonPath('pago.id_verificado_por', $usuario->id_usuario)
            ->assertJsonPath('pago.observaciones', 'El comprobante no coincide');

        $this->assertDatabaseHas('pagos', [
            'id_pago'           => $pago->id_pago,
            'estado'            => 'rechazado',
            'id_verificado_por' => $usuario->id_usuario,
            'observaciones'     => 'El comprobante no coincide',
        ]);
    }

    public function test_empleado_no_puede_verificar_un_pago(): void
    {
        [$restaurante, $administrador, $pedido] = $this->crearDatosDePrueba();

        // Crear un empleado.
        $empleado = Usuario::create([
            'nombre'     => 'Empleado Test',
            'correo'     => 'empleado.test@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado'     => 'activo',
        ]);

        // Asociarlo al restaurante con el rol empleado.
        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_usuario'     => $empleado->id_usuario,
            'rol'            => 'empleado',
        ]);

        // Crear un pago pendiente.
        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
        ]);

        // El empleado intenta verificarlo.
        $respuesta = $this->actingAs($empleado, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                ['estado' => 'verificado']
            );

        $respuesta->assertForbidden();

        // El pago debe seguir pendiente.
        $this->assertDatabaseHas('pagos', [
            'id_pago' => $pago->id_pago,
            'estado'  => 'pendiente',
        ]);
    }

    public function test_usuario_de_otro_restaurante_no_puede_consultar_sus_pagos(): void
    {
        [$restaurante, $administrador, $pedido] = $this->crearDatosDePrueba();

        // Crear un segundo restaurante.
        $otroRestaurante = Restaurante::create([
            'nombre' => 'Otro Restaurante Test',
            'slug'   => 'otro-restaurante-test',
            'estado' => 'activo',
        ]);

        // Crear un administrador que solo pertenece al segundo restaurante.
        $otroUsuario = Usuario::create([
            'nombre'     => 'Otro Administrador Test',
            'correo'     => 'otro.admin.test@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado'     => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario'     => $otroUsuario->id_usuario,
            'rol'            => 'administrador',
        ]);

        // Crear un pago en el primer restaurante.
        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
        ]);

        // El usuario del segundo restaurante intenta consultar los pagos del primero.
        $respuesta = $this->actingAs($otroUsuario, 'sanctum')
            ->getJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}"
            );

        $respuesta->assertForbidden();
    }

    public function test_pago_rechazado_libera_el_saldo_del_pedido(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        // Registrar un abono pendiente de $10.000.
        $respuestaInicial = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 10000,
                ]
            );

        $respuestaInicial->assertCreated();

        $idPago = $respuestaInicial->json('pago.id_pago');

        // Rechazar el primer pago.
        $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$idPago}/verificar",
                [
                    'estado'        => 'rechazado',
                    'observaciones' => 'Comprobante inválido',
                ]
            )
            ->assertOk();

        // Ahora debe permitirse registrar un pago de $15.000.
        $respuestaFinal = $this->actingAs($usuario, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 15000,
                ]
            );

        $respuestaFinal
            ->assertCreated()
            ->assertJsonPath('pago.estado', 'pendiente')
            ->assertJsonPath('pago.monto', '15000.00');

        // Deben existir los dos registros: el rechazado y el nuevo pendiente.
        $this->assertDatabaseCount('pagos', 2);

        $this->assertDatabaseHas('pagos', [
            'id_pago' => $idPago,
            'estado'  => 'rechazado',
        ]);
    }

    public function test_no_permite_registrar_pagos_para_pedidos_cancelados_o_entregados(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        foreach (['cancelado', 'entregado'] as $estado) {
            $pedido->estado = $estado;
            $pedido->save();

            $respuesta = $this->actingAs($usuario, 'sanctum')
                ->postJson(
                    "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                    [
                        'id_pedido'   => $pedido->id_pedido,
                        'metodo_pago' => 'transferencia',
                        'monto'       => 5000,
                    ]
                );

            $respuesta->assertStatus(422);
        }

        // No debe haberse creado ningún pago.
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_permite_registrar_pago_para_pedido_de_otro_restaurante(): void
    {
        [$restaurante, $administrador, $pedido] = $this->crearDatosDePrueba();

        // Crear otro restaurante.
        $otroRestaurante = Restaurante::create([
            'nombre' => 'Segundo Restaurante Test',
            'slug'   => 'segundo-restaurante-test',
            'estado' => 'activo',
        ]);

        // Asociar al administrador con el segundo restaurante también.
        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario'     => $administrador->id_usuario,
            'rol'            => 'administrador',
        ]);

        // Intentar registrar en el segundo restaurante un pago
        // que utiliza el pedido del primero.
        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->postJson(
                "/api/restaurantes/{$otroRestaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 5000,
                ]
            );

        $respuesta->assertUnprocessable();

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_permite_estados_invalidos_al_verificar_un_pago(): void
    {
        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $pago = \App\Models\Pago::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'transferencia',
            'monto'          => 5000,
            'estado'         => 'pendiente',
        ]);

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->patchJson(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos/{$pago->id_pago}/verificar",
                [
                    'estado' => 'aprobado',
                ]
            );

        $respuesta->assertUnprocessable();

        $this->assertDatabaseHas('pagos', [
            'id_pago' => $pago->id_pago,
            'estado'  => 'pendiente',
        ]);
    }

    public function test_administrador_puede_descargar_comprobante_privado(): void
    {
        Storage::fake('local');

        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $ruta = "comprobantes/{$restaurante->id_restaurante}/comprobante.pdf";

        Storage::disk('local')->put($ruta, '%PDF-1.4 comprobante de prueba');

        $pago = \App\Models\Pago::create([
            'id_restaurante'  => $restaurante->id_restaurante,
            'id_pedido'       => $pedido->id_pedido,
            'metodo_pago'     => 'transferencia',
            'monto'           => 5000,
            'estado'          => 'pendiente',
            'comprobante_url' => $ruta,
        ]);

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->get(
                "/api/restaurantes/{$restaurante->id_restaurante}" .
                "/pagos/{$pago->id_pago}/comprobante"
            );

        $respuesta->assertOk();
        $respuesta->assertHeader(
            'content-disposition',
            'attachment; filename=comprobante.pdf'
        );
    }

    public function test_usuario_de_otro_restaurante_no_puede_descargar_comprobante(): void
    {
        Storage::fake('local');

        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $otroRestaurante = Restaurante::create([
            'nombre' => 'Otro Restaurante',
            'slug'   => 'otro-restaurante',
            'estado' => 'activo',
        ]);

        $otroUsuario = Usuario::create([
            'nombre'     => 'Administrador Otro',
            'correo'     => 'otro.admin@example.com',
            'contrasena' => bcrypt('password-test'),
            'estado'     => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario'     => $otroUsuario->id_usuario,
            'rol'            => 'administrador',
        ]);

        $ruta = "comprobantes/{$restaurante->id_restaurante}/comprobante.pdf";

        Storage::disk('local')->put($ruta, '%PDF-1.4 comprobante de prueba');

        $pago = \App\Models\Pago::create([
            'id_restaurante'  => $restaurante->id_restaurante,
            'id_pedido'       => $pedido->id_pedido,
            'metodo_pago'     => 'transferencia',
            'monto'           => 5000,
            'estado'          => 'pendiente',
            'comprobante_url' => $ruta,
        ]);

        $respuesta = $this->actingAs($otroUsuario, 'sanctum')
            ->get(
                "/api/restaurantes/{$restaurante->id_restaurante}" .
                "/pagos/{$pago->id_pago}/comprobante"
            );

        $respuesta->assertForbidden();
    }

    public function test_no_permite_subir_archivos_invalidos_como_comprobante(): void
    {
        Storage::fake('local');

        [$restaurante, $usuario, $pedido] = $this->crearDatosDePrueba();

        $respuesta = $this->actingAs($usuario, 'sanctum')
            ->post(
                "/api/restaurantes/{$restaurante->id_restaurante}/pagos",
                [
                    'id_pedido'   => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                    'monto'       => 5000,
                    'comprobante' => UploadedFile::fake()->create(
                        'programa.exe',
                        100,
                        'application/octet-stream'
                    ),
                ],
                ['Accept' => 'application/json']
            );

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonValidationErrors('comprobante');
    }

}
