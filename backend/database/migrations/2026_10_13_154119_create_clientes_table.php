
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id_cliente');

            $table->unsignedInteger('id_restaurante');

            $table->string('nombre', 150);
            $table->string('telefono', 30);
            $table->string('correo', 150)->nullable();

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            $table->index(
                ['id_restaurante', 'telefono'],
                'idx_clientes_restaurante_telefono'
            );

            // Permite referenciar al cliente junto con su restaurante.
            $table->unique(
                ['id_restaurante', 'id_cliente'],
                'uq_clientes_restaurante_id'
            );

            $table->foreign('id_restaurante', 'fk_clientes_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};