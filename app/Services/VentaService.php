<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Services\ProductoService;
use App\Exceptions\VentaServiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VentaService
{
    private ProductoService $productoService;

    public function __construct(ProductoService $productoService)
    {
        $this->productoService = $productoService;
    }

    /**
     * crea una nueva venta con todos sus detalles:
     * 1) valida cliente
     * 2) oobtiene los precios de productos (HTTP)
     * 3) crea venta y detalles usando una transacción     * 
     */
    public function crearVenta(array $data): Venta
    {
        /*
        Log::info('VentaService: Iniciando creación de venta', [
            'cliente_id' => $data['cliente_id'],
            'items_count' => count($data['items']),
            'medio_pago' => $data['medio_pago']
        ]);
        */

        try {
            return DB::transaction(function () use ($data) {

                $cliente = $this->validarCliente($data['cliente_id']);

                $productos = $this->obtenerYValidarProductos($data['items']);

                $costoTotal = $this->calcularCostoTotal($productos);

                $venta = $this->crearVentaPrincipal($data, $costoTotal);

                $this->crearDetallesVenta($venta, $productos);

                return $venta->load(['cliente', 'detalleVentas']);
            });

        } catch (\Exception $e) {
            /*
            Log::error('VentaService: Error al crear venta', [
                'cliente_id' => $data['cliente_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            */
            // excepción personalizada 
            if ($e instanceof VentaServiceException) {
                throw $e;
            }

            throw new VentaServiceException(
                'Error interno al procesar la venta: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    public function actualizarVenta(Venta $venta, array $data): Venta
    {
        try {
            return DB::transaction(function () use ($venta, $data) {

                $cliente = $this->validarCliente($data['cliente_id']);

                $productos = $this->obtenerYValidarProductos($data['items']);

                $costoTotal = $this->calcularCostoTotal($productos);

                $venta->update([
                    'fecha' => $data['fecha'] ?? $venta->fecha,
                    'medio_pago' => $data['medio_pago'],
                    'id_cliente' => $data['cliente_id'],
                    'costo_total' => $costoTotal
                ]);

                $venta->detalleVentas()->delete();
                $this->crearDetallesVenta($venta, $productos);

                return $venta->load(['cliente', 'detalleVentas']);
            });

        } catch (\Exception $e) {

            throw new VentaServiceException(
                'Error al actualizar la venta: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    public function eliminarVenta(Venta $venta): bool
    {
        try {
            return DB::transaction(function () use ($venta) {

                $venta->detalleVentas()->delete(); //soft delete
                
                $eliminada = $venta->delete(); // soft delete

                return $eliminada;
            });

        } catch (\Exception $e) {
            /*
            Log::error('VentaService: Error al eliminar la venta', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage()
            ]);
            */
            
            // excepción personalizada
            throw new VentaServiceException(
                'Error al eliminar la venta: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }


    private function validarCliente(int $clienteId): Cliente
    {
        $cliente = Cliente::find($clienteId);

        if (!$cliente) {
            throw new VentaServiceException(
                "Cliente con ID {$clienteId} no encontrado o fue eliminado.",
                404
            );
        }

        return $cliente;
    }

    private function obtenerYValidarProductos(array $items): array
    {
        try {

            $productos = $this->productoService->obtenerProductosEnLote($items);

            if (count($productos) !== count($items)) {
                throw new VentaServiceException(
                    'No se pudieron obtener todos los productos solicitados.',
                    400
                );
            }

            foreach ($productos as $producto) {
                if (!isset($producto['precio']) || $producto['precio'] <= 0) {
                    throw new VentaServiceException(
                        "Producto {$producto['tipo']} ID {$producto['id']} tiene precio inválido.",
                        400
                    );
                }
            }

            return $productos;

        } catch (\App\Exceptions\ProductoServiceException $e) {
            throw new VentaServiceException(
                'Error al obtener información de productos: ' . $e->getMessage(),
                $e->getHttpCode(),
                $e
            );
        }
    }

    private function calcularCostoTotal(array $productos): float
    {
        return $this->productoService->calcularCostoTotal($productos);
    }

    private function crearVentaPrincipal(array $data, float $costoTotal): Venta
    {
        return Venta::create([
            'fecha' => $data['fecha'] ?? now()->format('Y-m-d'),
            'medio_pago' => $data['medio_pago'],
            'id_cliente' => $data['cliente_id'],
            'costo_total' => $costoTotal
        ]);
    }

    private function crearDetallesVenta(Venta $venta, array $productos): void
    {
        foreach ($productos as $producto) {
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $producto['id'],
                'tipo' => $producto['tipo'],
                'precio_unitario' => $producto['precio']
            ]);
        }
    }

    public function obtenerEstadisticas(array $filtros = []): array
    {
        $query = Venta::query();

        if (!empty($filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['cliente_id'])) {
            $query->where('id_cliente', $filtros['cliente_id']);
        }

        return [
            'total_ventas' => $query->count(),
            'monto_total' => $query->sum('costo_total'),
            'promedio_venta' => $query->avg('costo_total'),
            'ventas_por_medio_pago' => $query->selectRaw('medio_pago, COUNT(*) as cantidad, SUM(costo_total) as total') 
                ->groupBy('medio_pago')
                ->get()
                ->toArray()
        ];
    }
}