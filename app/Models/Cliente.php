<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class Cliente extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'email',
    ];

    protected $cast = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Un cliente puede tener muchas ventas
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    // accesor para nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";        
    }

    //scope para buscar por dni
    public function scopePorDni($query, string $dni)
    {
        return $query->where('dni', $dni);
    }


}
