<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Traits\NormalizaClienteData;

class ClienteUpdateRequest extends FormRequest
{

    use NormalizaClienteData;

    public function authorize(): bool
    {
        return true;
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
