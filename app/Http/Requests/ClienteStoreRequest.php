<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|string|size:8|unique:clientes,dni',
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
