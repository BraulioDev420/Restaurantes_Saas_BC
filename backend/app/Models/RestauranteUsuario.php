<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestauranteUsuario extends Model
{
    protected $table = 'restaurantes_usuarios';

    protected $primaryKey = 'id_restaurante_usuario';

    public $timestamps = false;

    protected $fillable = [
        'id_restaurante',
        'id_usuario',
        'rol',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
    ];

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(
            Restaurante::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id_usuario'
        );
    }
}