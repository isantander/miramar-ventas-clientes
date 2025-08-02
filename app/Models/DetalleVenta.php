<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends Model
{
    protected $fillable = [
        'venta_id',
        'producto_id',
        'tipo',
        'precio_unitario',
    ];

    protected $cast = [
        'precio_unitario' => 'decimal:2',
    ];

    // un detalle pertenece a una veta
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    //scope para filtrar por tipo de producto
    public function scopePorTipo($query, $tipo)
    {    
        return $query->where('tipo', $tipo);
    }

    // scope para servicios
    public function scopeServicios($query)
    {
        return $query->where('tipo', 'servicio');
    }

    // scope para paquetes
    public function scopePaquetes($query) {
        return $query->where('tipo', 'paquete');
    }
}
