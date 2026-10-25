<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoTest extends TestCase
{
    use RefreshDatabase;

    private function crearPedido(float $total = 15000): Pedido
    {
        $restaurante = Restaurante::create([
            'nombre' => 'Restaurante Pedido Test',
            'slug' => 'restaurante-pedido-test',
            'estado' => 'activo',
        ]);

        return Pedido::create([
            'id_restaurante' => $restaurante->id_restaurante,
            'numero_pedido' => 'PED-TEST-001',
            'nombre_cliente' => 'Cliente Test',
            'telefono_cliente' => '3001234567',
            'tipo_entrega' => 'recoger',
            'subtotal' => $total,
            'costo_domicilio' => 0,
            'total' => $total,
            'estado' => 'pendiente',
        ]);
    }

    private function crearPago(
        Pedido $pedido,
        float $monto,
        string $estado
    ): Pago {
        return Pago::create([
            'id_restaurante' => $pedido->id_restaurante,
            'id_pedido' => $pedido->id_pedido,
            'metodo_pago' => 'transferencia',
            'monto' => $monto,
            'estado' => $estado,
        ]);
    }

    public function test_pedido_sin_pagos_tiene_saldo_pendiente_completo(): void
    {
        $pedido = $this->crearPedido();

        $pedido->refresh();

        $this->assertSame('0.00', $pedido->total_pagado);
        $this->assertSame('15000.00', $pedido->saldo_pendiente);
        $this->assertSame('pendiente', $pedido->estado_pago);
    }

    public function test_solo_los_pagos_verificados_se_cuentan_como_pagados(): void
    {
        $pedido = $this->crearPedido();

        $this->crearPago($pedido, 3000, 'verificado');
        $this->crearPago($pedido, 2000, 'pendiente');
        $this->crearPago($pedido, 1000, 'rechazado');

        $pedido->refresh();

        $this->assertSame('3000.00', $pedido->total_pagado);
        $this->assertSame('12000.00', $pedido->saldo_pendiente);
        $this->assertSame('parcial', $pedido->estado_pago);
    }

    public function test_pedido_completamente_pagado_tiene_saldo_cero(): void
    {
        $pedido = $this->crearPedido(15000);

        $this->crearPago($pedido, 10000, 'verificado');
        $this->crearPago($pedido, 5000, 'verificado');

        $pedido->refresh();

        $this->assertSame('15000.00', $pedido->total_pagado);
        $this->assertSame('0.00', $pedido->saldo_pendiente);
        $this->assertSame('pagado', $pedido->estado_pago);
    }
}
