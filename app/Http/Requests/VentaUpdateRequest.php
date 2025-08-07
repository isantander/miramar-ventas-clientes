<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VentaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_cliente' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('clientes', 'id')->whereNull('deleted_at')
            ],
            'medio_pago' => [
                'required',
                'string',
                'max:100',
                'min:3'
            ],
            'fecha' => [
                'required',   
                'date_format:Y-m-d',
                'before_or_equal:today'
            ],            
            // array de items - para update según la especificación
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50'
            ],
            'items.*.id_detalle_venta' => [
                'sometimes',
                'integer',
                'min:1'
            ],
            'items.*.producto_id' => [
                'required',
                'integer',
                'min:1'
            ],
            'items.*.tipo' => [
                'required',
                'string',
                Rule::in(['servicio', 'paquete'])
            ]
        ];
    }

    public function messages(): array
    {
        return [

            'id_cliente.required' => 'El cliente es obligatorio.',
            'id_cliente.integer' => 'El ID del cliente debe ser un número entero.',
            'id_cliente.exists' => 'El cliente seleccionado no existe o fue eliminado.',
            
            'medio_pago.required' => 'El medio de pago es obligatorio.',
            'medio_pago.string' => 'El medio de pago debe ser texto.',
            'medio_pago.max' => 'El medio de pago no puede superar los 100 caracteres.',
            'medio_pago.min' => 'El medio de pago debe tener al menos 3 caracteres.',
            
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            
            'items.required' => 'Debe incluir al menos un producto en la venta.',
            'items.array' => 'Los items deben enviarse como un array.',
            'items.min' => 'Debe incluir al menos un producto.',
            'items.max' => 'No se pueden incluir más de 50 productos por venta.',
            
            'items.*.id_detalle_venta.integer' => 'El ID del detalle de venta debe ser un número entero.',
            'items.*.id_detalle_venta.min' => 'El ID del detalle de venta debe ser mayor a 0.',
            'items.*.producto_id.required' => 'Cada item debe incluir un ID de producto.',
            'items.*.producto_id.integer' => 'El ID del producto debe ser un número entero.',
            'items.*.producto_id.min' => 'El ID del producto debe ser mayor a 0.',
            
            'items.*.tipo.required' => 'Cada item debe especificar el tipo de producto.',
            'items.*.tipo.string' => 'El tipo de producto debe ser texto.',
            'items.*.tipo.in' => 'El tipo debe ser "servicio" o "paquete".',
        ];
    }


    public function attributes(): array
    {
        return [
            'id_cliente' => 'cliente',
            'medio_pago' => 'medio de pago',
            'fecha' => 'fecha',
            'items' => 'productos',
            'items.*.id_detalle_venta' => 'ID del detalle',
            'items.*.producto_id' => 'ID del producto',
            'items.*.tipo' => 'tipo de producto',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([

            'medio_pago' => $this->medio_pago ? 
                ucfirst(trim(strtolower($this->medio_pago))) : null,

            'fecha' => $this->fecha ? 
                trim($this->fecha) : null,
            
            'items' => collect($this->items ?? [])->map(function ($item) {
                $normalized = [
                    'producto_id' => $item['producto_id'] ?? null,
                    'tipo' => isset($item['tipo']) ? 
                        trim(strtolower($item['tipo'])) : null
                ];
                
                // incluir id_detalle_venta si esta presente (si es update)
                if (isset($item['id_detalle_venta'])) {
                    $normalized['id_detalle_venta'] = $item['id_detalle_venta'];
                }
                
                return $normalized;
            })->toArray()
        ]);
    }


    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        /*
        if (config('app.debug')) {
            \Log::info('VentaUpdate error de validacion', [
                'errors' => $validator->errors()->toArray(),
                'input' => $this->all(),
                'venta_id' => $this->route('venta')?->id
            ]);
        }
        */

        parent::failedValidation($validator);
    }

    public function getValidatedForService(): array
    {
        $validated = $this->validated();
        
        return [
            'cliente_id' => $validated['id_cliente'],
            'medio_pago' => $validated['medio_pago'],
            'fecha' => $validated['fecha'] ?? null, // es opcional en update
            'items' => collect($validated['items'])->map(function ($item) {
                $result = [
                    'producto_id' => $item['producto_id'],
                    'tipo' => $item['tipo']
                ];
                
                // incluir el id_detalle_venta si existe, aunque no se use en el servicio actual
                if (isset($item['id_detalle_venta'])) {
                    $result['id_detalle_venta'] = $item['id_detalle_venta'];
                }
                
                return $result;
            })->toArray()
        ];
    }
}