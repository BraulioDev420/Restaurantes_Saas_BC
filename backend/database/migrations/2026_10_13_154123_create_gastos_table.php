
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->increments('id_gasto');

            $table->unsignedInteger('id_restaurante');

            $table->string('descripcion', 255);
            $table->string('categoria', 50);
            $table->decimal('monto', 12, 2);
            $table->date('fecha_gasto');

            $table->string('comprobante_url', 500)->nullable();

            $table->unsignedInteger('id_creado_por');

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            $table->index(
                ['id_restaurante', 'fecha_gasto'],
                'idx_gastos_restaurante_fecha'
            );

            $table->index('id_creado_por', 'idx_gastos_creador');

            $table->foreign('id_restaurante', 'fk_gastos_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // El usuario que registra el gasto debe pertenecer
            // al restaurante al que se imputa ese gasto.
            $table->foreign(
                ['id_restaurante', 'id_creado_por'],
                'fk_gastos_creador_restaurante'
            )
                ->references(['id_restaurante', 'id_usuario'])
                ->on('restaurantes_usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};