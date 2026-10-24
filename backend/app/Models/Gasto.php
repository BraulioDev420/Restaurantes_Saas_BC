<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $table = 'gastos';

    protected $primaryKey = 'id_gasto';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id_restaurante',
        'descripcion',
        'categoria',
        'monto',
        'fecha_gasto',
        'comprobante_url',
        'id_creado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_gasto' => 'date',
    ];

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(
            Restaurante::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'id_creado_por',
            'id_usuario'
        );
    }
}