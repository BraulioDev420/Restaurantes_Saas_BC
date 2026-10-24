<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $primaryKey = 'id_pago';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id_restaurante',
        'id_pedido',
        'metodo_pago',
        'monto',
        'comprobante_url',
        'estado',
        'observaciones',
        'id_verificado_por',
        'verificado_en',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'verificado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            Pedido::class,
            'id_pedido',
            'id_pedido'
        );
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'id_verificado_por',
            'id_usuario'
        );
    }
}