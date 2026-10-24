<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Restaurante extends Model
{
    protected $table = 'restaurantes';

    protected $primaryKey = 'id_restaurante';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'logo_url',
        'telefono',
        'correo',
        'direccion',
        'estado',
    ];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'restaurantes_usuarios',
            'id_restaurante',
            'id_usuario',
            'id_restaurante',
            'id_usuario'
        )->withPivot('id_restaurante_usuario', 'rol', 'creado_en');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(
            Categoria::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(
            Cliente::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(
            Pedido::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(
            Gasto::class,
            'id_restaurante',
            'id_restaurante'
        );
    }
}