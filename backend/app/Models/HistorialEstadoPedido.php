<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstadoPedido extends Model
{
    protected $table = 'historial_estados_pedido';

    protected $primaryKey = 'id_historial_estado';

    public $timestamps = false;

    protected $fillable = [
        'id_restaurante',
        'id_pedido',
        'id_cambiado_por',
        'estado',
        'notas',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            Pedido::class,
            'id_pedido',
            'id_pedido'
        );
    }

    public function cambiadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'id_cambiado_por',
            'id_usuario'
        );
    }
}