<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
{
    $data = [];
    
    // normalizar dni para que solo acepte números
    if ($this->has('dni')) {
        $dni = preg_replace('/[^0-9]/', '', $this->dni);
        $data['dni'] = (int) $dni;
    }
    
    // email en minúsculas y borrado de espacios en blanco
    if ($this->has('email')) {
        $data['email'] = strtolower(trim($this->email));
    }
    
    // nombre en mayúsculas y sin espacios en blanco extras
    if ($this->has('nombre')) {
        $data['nombre'] = strtoupper(trim($this->nombre));
    }
    
    // apellido en mayúsculas y sin espacios en blanco extras
    if ($this->has('apellido')) {
        $data['apellido'] = strtoupper(trim($this->apellido));
    }
    
    $this->merge($data);
}

    public function rules(): array
    {
        $clienteId = $this->route('cliente');
        
        return [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|integer|between:1000000,99999999|unique:clientes,dni',
            'email' => 'required|email|max:255|unique:clientes,email,' . $clienteId,
        ];        
    }

    public function messages(): array
    {
        return [
            'dni.size' => 'El DNI máximo permitido es 99999999',
            'dni.unique' => 'Ya existe un cliente con este DNI',
            'email.unique' => 'Ya existe un cliente con este email',
        ];
    }
}
