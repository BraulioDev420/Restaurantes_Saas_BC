<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model
{
    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'correo',
        'contrasena',
        'estado',
    ];

    protected $hidden = [
        'contrasena',
    ];

    public function restaurantes(): BelongsToMany
    {
        return $this->belongsToMany(
            Restaurante::class,
            'restaurantes_usuarios',
            'id_usuario',
            'id_restaurante',
            'id_usuario',
            'id_restaurante'
        )->withPivot('id_restaurante_usuario', 'rol', 'creado_en');
    }
}