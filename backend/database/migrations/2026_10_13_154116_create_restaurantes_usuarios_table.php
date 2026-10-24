
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurantes_usuarios', function (Blueprint $table) {
            $table->increments('id_restaurante_usuario');
            $table->unsignedInteger('id_restaurante');
            $table->unsignedInteger('id_usuario');
            $table->string('rol', 30);
            $table->timestamp('creado_en')->useCurrent();

            $table->unique(
                ['id_restaurante', 'id_usuario'],
                'uq_restaurante_usuario'
            );

            $table->index('id_usuario', 'idx_ru_usuario');

            $table->foreign('id_restaurante')
                ->references('id_restaurante')
                ->on('restaurantes');

            $table->foreign('id_usuario')
                ->references('id_usuario')
                ->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurantes_usuarios');
    }
};