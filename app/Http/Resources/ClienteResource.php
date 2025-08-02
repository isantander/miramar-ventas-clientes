<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => $this->nombre_completo, // accesor del modelo
            'dni' => $this->dni,
            'email' => $this->email,
            'creado_en' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
