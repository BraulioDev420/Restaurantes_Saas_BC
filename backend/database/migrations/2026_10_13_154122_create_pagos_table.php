
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->increments('id_pago');

            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_pedido');

            $table->string('metodo_pago', 30);
            $table->decimal('monto', 12, 2);

            // Guardar una ruta privada, no hacer público el comprobante.
            $table->string('comprobante_url', 500)->nullable();

            $table->string('estado', 30)->default('pendiente');
            $table->string('observaciones', 255)->nullable();

            $table->unsignedInteger('id_verificado_por')->nullable();
            $table->timestamp('verificado_en')->nullable();

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            $table->index(
                ['id_pedido', 'estado'],
                'idx_pagos_pedido_estado'
            );

            $table->index(
                'id_verificado_por',
                'idx_pagos_verificador'
            );

            // El pago debe corresponder al pedido del mismo restaurante.
            $table->foreign(
                ['id_restaurante', 'id_pedido'],
                'fk_pagos_pedido_restaurante'
            )
                ->references(['id_restaurante', 'id_pedido'])
                ->on('pedidos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Solo un usuario asociado a ese restaurante puede verificarlo.
            $table->foreign(
                ['id_restaurante', 'id_verificado_por'],
                'fk_pagos_verificador_restaurante'
            )
                ->references(['id_restaurante', 'id_usuario'])
                ->on('restaurantes_usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};