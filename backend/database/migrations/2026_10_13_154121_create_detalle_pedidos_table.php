
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pedidos', function (Blueprint $table) {
            $table->increments('id_detalle_pedido');

            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_pedido');
            $table->unsignedInteger('id_producto')->nullable();

            // Datos históricos del producto al momento de comprar.
            $table->string('nombre_producto', 150);
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('observaciones', 255)->nullable();

            $table->index('id_pedido', 'idx_detalle_pedidos_pedido');
            $table->index('id_producto', 'idx_detalle_pedidos_producto');

            // El pedido debe pertenecer al restaurante indicado.
            $table->foreign(
                ['id_restaurante', 'id_pedido'],
                'fk_detalle_pedido_restaurante'
            )
                ->references(['id_restaurante', 'id_pedido'])
                ->on('pedidos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Si se referencia un producto, debe ser del mismo restaurante.
            $table->foreign(
                ['id_restaurante', 'id_producto'],
                'fk_detalle_producto_restaurante'
            )
                ->references(['id_restaurante', 'id_producto'])
                ->on('productos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedidos');
    }
};