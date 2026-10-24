
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_estados_pedido', function (Blueprint $table) {
            $table->increments('id_historial_estado');

            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_pedido');

            // Puede ser NULL cuando el cambio lo genere el sistema.
            $table->unsignedInteger('id_cambiado_por')->nullable();

            $table->string('estado', 30);
            $table->text('notas')->nullable();

            $table->timestamp('creado_en')->useCurrent();

            $table->index(
                ['id_pedido', 'creado_en'],
                'idx_historial_pedido_fecha'
            );

            $table->index(
                'id_cambiado_por',
                'idx_historial_usuario'
            );

            // El historial y el pedido deben pertenecer al mismo restaurante.
            $table->foreign(
                ['id_restaurante', 'id_pedido'],
                'fk_historial_pedido_restaurante'
            )
                ->references(['id_restaurante', 'id_pedido'])
                ->on('pedidos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // El usuario que cambia el estado debe pertenecer al restaurante.
            // NULL permite registrar cambios automáticos del sistema.
            $table->foreign(
                ['id_restaurante', 'id_cambiado_por'],
                'fk_historial_usuario_restaurante'
            )
                ->references(['id_restaurante', 'id_usuario'])
                ->on('restaurantes_usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_pedido');
    }
};