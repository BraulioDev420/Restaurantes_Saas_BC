
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->increments('id_pedido');

            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_cliente')->nullable();

            $table->string('numero_pedido', 30);
            $table->string('nombre_cliente', 150);
            $table->string('telefono_cliente', 30);
            $table->string('correo_cliente', 150)->nullable();

            $table->string('tipo_entrega', 20);
            $table->string('direccion_entrega', 255)->nullable();
            $table->text('notas')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('costo_domicilio', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('estado', 30)->default('pendiente');

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            $table->unique(
                ['id_restaurante', 'numero_pedido'],
                'uq_pedidos_restaurante_numero'
            );

            // Clave candidata para las relaciones compuestas.
            $table->unique(
                ['id_restaurante', 'id_pedido'],
                'uq_pedidos_restaurante_id'
            );

            $table->index(
                ['id_restaurante', 'estado', 'creado_en'],
                'idx_pedidos_restaurante_estado_fecha'
            );

            $table->index('id_cliente', 'idx_pedidos_cliente');

            $table->foreign('id_restaurante', 'fk_pedidos_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Si hay cliente registrado, debe pertenecer al restaurante
            // que recibe el pedido. Si es invitado, id_cliente será NULL.
            $table->foreign(
                ['id_restaurante', 'id_cliente'],
                'fk_pedidos_cliente_restaurante'
            )
                ->references(['id_restaurante', 'id_cliente'])
                ->on('clientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};