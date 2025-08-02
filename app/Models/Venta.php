<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fecha',
        'medio_pago',
        'id_cliente',
        'costo_total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'costo_total' => 'decimal:2',
    ];
    
    // una venta perfecta a un cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    // una venta tiene muchos detalles 
    /* public function detalle(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta');
    } */

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    // scope para ventas por fecha
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    // scope para ventas por cliente
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('id_cliente', $clienteId);
    }
    
}
