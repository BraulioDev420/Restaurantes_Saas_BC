<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    private ?int $totalPagadoCentavosCache = null;

    protected $table = 'pedidos';

    protected $primaryKey = 'id_pedido';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'id_restaurante',
        'id_cliente',
        'numero_pedido',
        'nombre_cliente',
        'telefono_cliente',
        'correo_cliente',
        'tipo_entrega',
        'direccion_entrega',
        'notas',
        'subtotal',
        'costo_domicilio',
        'total',
        'estado',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'costo_domicilio' => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    protected $appends = [
        'total_pagado',
        'saldo_pendiente',
        'estado_pago',
    ];

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(
            Restaurante::class,
            'id_restaurante',
            'id_restaurante'
        );
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Cliente::class,
            'id_cliente',
            'id_cliente'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetallePedido::class,
            'id_pedido',
            'id_pedido'
        );
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(
            Pago::class,
            'id_pedido',
            'id_pedido'
        );
    }

    private function obtenerTotalPagadoCentavos(): int
    {
        if ($this->totalPagadoCentavosCache !== null) {
            return $this->totalPagadoCentavosCache;
        }

        if (array_key_exists('pagos_verificados_sum_monto', $this->attributes)) {
            $total = $this->attributes['pagos_verificados_sum_monto'];
        } else {
            $total = $this->pagos()
                ->where('estado', 'verificado')
                ->sum('monto');
        }

        $this->totalPagadoCentavosCache = (int) round(
            (float) $total * 100
        );

        return $this->totalPagadoCentavosCache;
    }

    public function getTotalPagadoAttribute(): string
    {
        return number_format(
            $this->obtenerTotalPagadoCentavos() / 100,
            2,
            '.',
            ''
        );
    }

    public function getSaldoPendienteAttribute(): string
    {
        $totalCentavos  = (int) round((float) $this->total * 100);
        $pagadoCentavos = $this->obtenerTotalPagadoCentavos();

        return number_format(
            max(0, $totalCentavos - $pagadoCentavos) / 100,
            2,
            '.',
            ''
        );
    }

    public function getEstadoPagoAttribute(): string
    {
        $totalCentavos  = (int) round((float) $this->total * 100);
        $pagadoCentavos = $this->obtenerTotalPagadoCentavos();

        if ($pagadoCentavos <= 0) {
            return 'pendiente';
        }

        if ($pagadoCentavos >= $totalCentavos) {
            return 'pagado';
        }

        return 'parcial';
    }

    public function historialEstados(): HasMany
    {
        return $this->hasMany(
            HistorialEstadoPedido::class,
            'id_pedido',
            'id_pedido'
        );
    }
}
