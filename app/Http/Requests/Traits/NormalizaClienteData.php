<?php

namespace App\Http\Requests\Traits;

trait NormalizaClienteData
{
    public function prepareForValidation()
    {
        $data = [];
        
        if ($this->has('dni')) {
            $dni = preg_replace('/[^0-9]/', '', $this->dni);
            $data['dni'] = (int) $dni;
        }
        
        if ($this->has('email')) {
            $data['email'] = strtolower(trim($this->email));
        }
        
        if ($this->has('nombre')) {
            $data['nombre'] = strtoupper(trim($this->nombre));
        }
        
        if ($this->has('apellido')) {
            $data['apellido'] = strtoupper(trim($this->apellido));
        }
        
        $this->merge($data);
    }
}
