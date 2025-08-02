<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Traits\NormalizaClienteData;

class ClienteStoreRequest extends FormRequest
{
    use NormalizaClienteData;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|integer|between:1000000,99999999|unique:clientes,dni',
            'email' => 'required|email|max:255|unique:clientes,email',
        ];
    }

    public function messages(): array
    {
        return [
            'dni.size' => 'El DNI debe tener máximo 8 caracteres',
            'dni.unique' => 'Ya existe un cliente con este DNI',
            'email.unique' => 'Ya existe un cliente con este email',
        ];
    }
}
