<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VentaStoreRequest extends FormRequest
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
            'fecha' => 'required|date',
            'medio_pago' => 'required|string|max:255',
            'id_cliente' => 'required|integer|exists:clientes,id',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|integer|min:1',
            'items.*.tipo' => 'required|string|in:servicio,paquete',
        ];
    }

    public function messages(): array
    {
        return [
            'id_cliente.exists' => 'El cliente seleccionado no existe',
            'items.required' => 'Debe incluir al menos un producto en la venta',
            'items.*.producto_id.required' => 'Cada item debe tener un producto_id',
            'items.*.tipo.in' => 'El tipo debe ser "servicio" o "paquete"',
        ];
    }
}
