<?php
namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Restaurante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private Restaurante $restaurante;
    private Usuario $administrador;
    private string $desde = '2026-10-01';
    private string $hasta = '2026-10-31';

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::create([
            'nombre' => 'Restaurante Reportes',
            'slug'   => 'restaurante-reportes-test',
            'estado' => 'activo',
        ]);

        $this->administrador = Usuario::create([
            'nombre'     => 'Administrador Reportes',
            'correo'     => uniqid('reportes_') . '@test.com',
            'contrasena' => bcrypt('password'),
            'estado'     => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $this->restaurante->id_restaurante,
            'id_usuario'     => $this->administrador->id_usuario,
            'rol'            => 'administrador',
            'creado_en'      => now(),

        ]);
    }

    private function crearPedido(
        string $estado = 'pendiente',
        string $fecha = '2026-10-15 12:00:00',
        string $total = '100000.00'
    ): Pedido {
        $pedido = Pedido::create([
            'id_restaurante'   => $this->restaurante->id_restaurante,
            'numero_pedido'    => uniqid('PED-'),
            'nombre_cliente'   => 'Cliente de prueba',
            'telefono_cliente' => '3001234567',
            'tipo_entrega'     => 'domicilio',
            'subtotal'         => $total,
            'costo_domicilio'  => '0.00',
            'total'            => $total,
            'estado'           => $estado,
        ]);

        $pedido->forceFill([
            'creado_en'      => $fecha,
            'actualizado_en' => $fecha,
        ])->save();

        return $pedido;
    }

    private function crearPago(
        Pedido $pedido,
        string $monto,
        string $estado,
        ?string $verificadoEn
    ): Pago {
        return Pago::create([
            'id_restaurante' => $this->restaurante->id_restaurante,
            'id_pedido'      => $pedido->id_pedido,
            'metodo_pago'    => 'efectivo',
            'monto'          => $monto,
            'estado'         => $estado,
            'verificado_en'  => $verificadoEn,
        ]);
    }

    private function crearGasto(
        string $monto,
        string $fecha = '2026-10-15'
    ): Gasto {
        return Gasto::create([
            'id_restaurante' => $this->restaurante->id_restaurante,
            'descripcion'    => 'Gasto de prueba',
            'categoria'      => 'Insumos',
            'monto'          => $monto,
            'fecha_gasto'    => $fecha,
            'id_creado_por'  => $this->administrador->id_usuario,
        ]);
    }

    private function consultarReporte(array $parametros = [])
    {
        Sanctum::actingAs($this->administrador);

        return $this->getJson(
            '/api/restaurantes/' .
            $this->restaurante->id_restaurante .
            '/reportes/resumen?' .
            http_build_query($parametros)
        );
    }

    public function test_reporte_calcula_ingresos_gastos_balance_y_pedidos(): void
    {
        $pedido = $this->crearPedido('pendiente', '2026-10-15 12:00:00', '100000.00');
        $this->crearPago($pedido, '40000.00', 'verificado', '2026-10-15 13:00:00');
        $this->crearPago($pedido, '10000.00', 'pendiente', null);

        $this->crearPedido('cancelado', '2026-10-16 12:00:00', '30000.00');
        $this->crearGasto('15000.00');

        $respuesta = $this->consultarReporte([
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('resumen.ingresos_verificados', '40000.00')
            ->assertJsonPath('resumen.gastos_registrados', '15000.00')
            ->assertJsonPath('resumen.balance_estimado', '25000.00')
            ->assertJsonPath('resumen.cantidad_pedidos', 2)
            ->assertJsonPath('resumen.valor_pedidos_no_cancelados', '100000.00');
    }

    public function test_no_cuenta_pagos_pendientes_o_rechazados(): void
    {
        $pedido = $this->crearPedido();

        $this->crearPago($pedido, '20000.00', 'pendiente', null);
        $this->crearPago($pedido, '15000.00', 'rechazado', null);

        $this->consultarReporte([
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ])->assertOk()
            ->assertJsonPath('resumen.ingresos_verificados', '0.00');
    }

    public function test_filtra_gastos_y_pedidos_por_periodo(): void
    {
        $this->crearPedido('pendiente', '2026-10-15 12:00:00', '50000.00');
        $this->crearPedido('pendiente', '2026-09-15 12:00:00', '70000.00');

        $this->crearGasto('10000.00', '2026-10-15');
        $this->crearGasto('9000.00', '2026-09-15');

        $this->consultarReporte([
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
        ])->assertOk()
            ->assertJsonPath('resumen.cantidad_pedidos', 1)
            ->assertJsonPath('resumen.valor_pedidos_no_cancelados', '50000.00')
            ->assertJsonPath('resumen.gastos_registrados', '10000.00');
    }

    public function test_agrupa_pedidos_por_estado_y_gastos_por_categoria(): void
    {
        $this->crearPedido('pendiente');
        $this->crearPedido('pendiente');
        $this->crearPedido('entregado');

        $this->crearGasto('10000.00');
        $this->crearGasto('5000.00');

        $respuesta = $this->consultarReporte([
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);

        $respuesta->assertOk()
            ->assertJsonFragment([
                'estado'   => 'pendiente',
                'cantidad' => 2,
            ])
            ->assertJsonFragment([
                'estado'   => 'entregado',
                'cantidad' => 1,
            ])
            ->assertJsonPath('gastos_por_categoria.0.categoria', 'Insumos')
            ->assertJsonPath('gastos_por_categoria.0.total', 15000);
    }

    public function test_rechaza_un_periodo_con_fechas_invertidas(): void
    {
        $this->consultarReporte([
            'desde' => '2026-10-31',
            'hasta' => '2026-10-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('desde');
    }

    public function test_rechaza_fechas_con_formato_invalido(): void
    {
        $this->consultarReporte([
            'desde' => '01-10-2026',
            'hasta' => '2026-10-31',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('desde');
    }

    public function test_usuario_no_autenticado_no_puede_consultar_reportes(): void
    {
        $this->getJson(
            '/api/restaurantes/' .
            $this->restaurante->id_restaurante .
            '/reportes/resumen'
        )->assertUnauthorized();
    }

    public function test_usuario_de_otro_restaurante_no_puede_consultar_reportes(): void
    {
        $otroRestaurante = Restaurante::create([
            'nombre' => 'Otro Restaurante',
            'slug'   => 'otro-restaurante-reportes',
            'estado' => 'activo',
        ]);

        $otroUsuario = Usuario::create([
            'nombre'     => 'Otro Administrador',
            'correo'     => uniqid('otro_reportes_') . '@test.com',
            'contrasena' => bcrypt('password'),
            'estado'     => 'activo',
        ]);

        DB::table('restaurantes_usuarios')->insert([
            'id_restaurante' => $otroRestaurante->id_restaurante,
            'id_usuario'     => $otroUsuario->id_usuario,
            'rol'            => 'administrador',
            'creado_en'      => now(),

        ]);

        Sanctum::actingAs($otroUsuario);

        $this->getJson(
            '/api/restaurantes/' .
            $this->restaurante->id_restaurante .
            '/reportes/resumen'
        )->assertForbidden();
    }
}
