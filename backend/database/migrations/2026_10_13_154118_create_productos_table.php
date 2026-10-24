
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->increments('id_producto');

            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_categoria');

            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 12, 2);
            $table->string('imagen_url', 500)->nullable();
            $table->boolean('disponible')->default(true);

            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('actualizado_en')->useCurrent();

            $table->index(
                ['id_restaurante', 'nombre'],
                'idx_productos_restaurante_nombre'
            );

            $table->index('id_categoria', 'idx_productos_categoria');

            // Clave compuesta necesaria para validar que la categoría
            // pertenezca al mismo restaurante que el producto.
            $table->foreign(
                ['id_restaurante', 'id_categoria'],
                'fk_productos_categoria_restaurante'
            )
                ->references(['id_restaurante', 'id_categoria'])
                ->on('categorias')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_restaurante', 'fk_productos_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Clave candidata para relaciones compuestas futuras.
            $table->unique(
                ['id_restaurante', 'id_producto'],
                'uq_productos_restaurante_id'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};