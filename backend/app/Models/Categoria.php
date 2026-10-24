<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id_restaurante',
        'nombre',
        'descripcion',
        'orden_menu',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden_menu' => 'integer',
    ];

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(
            Restaurante::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'id_categoria',
            'id_categoria'
        );
    }
}