
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->increments('id_categoria');

            $table->unsignedInteger('id_restaurante');

            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->integer('orden_menu')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            // Evita nombres repetidos dentro del mismo restaurante.
            $table->unique(
                ['id_restaurante', 'nombre'],
                'uq_categorias_restaurante_nombre'
            );

            // Permite validar la pertenencia de la categoría
            // mediante claves foráneas compuestas.
            $table->unique(
                ['id_restaurante', 'id_categoria'],
                'uq_categorias_restaurante_id'
            );

            $table->foreign('id_restaurante', 'fk_categorias_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};