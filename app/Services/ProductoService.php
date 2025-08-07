<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use App\Exceptions\ProductoServiceException;

class ProductoService
{
    private string $baseUrl;
    private int $timeout;
    private int $retryTimes;

    public function __construct()
    {
        $this->baseUrl = config('services.productos.url', 'http://localhost:8010');
        $this->timeout = config('services.productos.timeout', 10); // 10 segundos
        $this->retryTimes = config('services.productos.retry', 3);
    }

    public function obtenerProducto(int $productoId, string $tipo): array
    {
        $this->validarTipo($tipo);

        try {
            $endpoint = $this->construirEndpoint($tipo, $productoId);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Service-Token' => config('services.internal.ventas_token'),
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'MiramarVentas/1.0'
                ])
                ->retry($this->retryTimes, 100) // Retry con 100ms de delay
                ->get($endpoint);

            if (!$response->successful()) {
                throw new ProductoServiceException(
                    "Error al consultar producto {$tipo} ID {$productoId}: " . $response->status(),
                    $response->status()
                );
            }

            $data = $response->json();
            $data = $data['data'] ?? $data; // Extraer contenido de 'data'  
            
            $this->validarRespuestaProducto($data, $tipo, $productoId);

            return $this->normalizarRespuestaProducto($data, $tipo);

        } catch (RequestException $e) {

            throw new ProductoServiceException(
                "No se pudo conectar con el servicio de productos: " . $e->getMessage(),
                503 // Service Unavailable
            );
        }
    }

    /**
     * Obtiene múltiples productos en una sola operación (batch)
     */
    public function obtenerProductosEnLote(array $items): array
    {
        $productos = [];
        $errores = [];

        foreach ($items as $index => $item) {
            try {
                $producto = $this->obtenerProducto(
                    $item['producto_id'], 
                    $item['tipo']
                );
                
                $productos[] = array_merge($producto, [
                    'item_index' => $index,
                    'producto_id_original' => $item['producto_id'],
                    'tipo_original' => $item['tipo']
                ]);

            } catch (ProductoServiceException $e) {
                $errores[] = [
                    'item_index' => $index,
                    'producto_id' => $item['producto_id'],
                    'tipo' => $item['tipo'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Si hay errores, lanzar excepción con detalles completos
        if (!empty($errores)) {
            /*
            Log::error("ProductoService: Errores en lote", [
                'errores' => $errores,
                'productos_exitosos' => count($productos)
            ]);
            */

            throw new ProductoServiceException(
                "Error al obtener algunos productos: " . json_encode($errores),
                400
            );
        }

        return $productos;
    }

    /**
     * Calcula el costo total de una lista de productos
     */
    public function calcularCostoTotal(array $productos): float
    {
        return collect($productos)->sum('precio');
    }

    private function validarTipo(string $tipo): void
    {
        if (!in_array($tipo, ['servicio', 'paquete'])) {
            throw new ProductoServiceException(
                "Tipo de producto inválido: {$tipo}. Debe ser 'servicio' o 'paquete'.",
                400
            );
        }
    }

    /**
     * construye el endpoint correcto según el tipo (servicio o paquete)
     */
    private function construirEndpoint(string $tipo, int $productoId): string
    {
        $pluralTipo = $tipo === 'servicio' ? 'servicios' : 'paquetes';
        // Usar endpoint interno para comunicación entre microservicios
        return "{$this->baseUrl}/api/internal/{$pluralTipo}/{$productoId}";
    }

    /**
     * valida que la respuesta del microservicio tenga la estructura correcta!
     */
    private function validarRespuestaProducto(array $data, string $tipo, int $productoId): void
    {
        if (!isset($data['id'])) {
            throw new ProductoServiceException(
                "Respuesta inválida: falta campo 'id' para {$tipo} {$productoId}",
                502
            );
        }

        if (!isset($data['costo']) && !isset($data['precio']) && !isset($data['precio_calculado'])) {
            throw new ProductoServiceException(
                "Respuesta inválida: falta campo de precio para {$tipo} {$productoId}",
                502
            );
        }

        if ((int)$data['id'] !== $productoId) {
            throw new ProductoServiceException(
                "ID de respuesta no coincide: esperado {$productoId}, recibido {$data['id']}",
                502
            );
        }
    }

    private function normalizarRespuestaProducto(array $data, string $tipo): array
    {
        return [
            'id' => $data['id'],
            'tipo' => $tipo,
            'nombre' => $data['nombre'] ?? 'Producto sin nombre',
            'precio' => (float)($data['costo'] ?? $data['precio'] ?? $data['precio_calculado']),
            'descripcion' => $data['descripcion'] ?? null,
            'data_completa' => $data // para debuging o uso futuro
        ];
    }

    /**
     * verifica la conectividad con el servicio de productos
     */
    public function verificarConectividad(): bool
    {
        try {
            // Usar health check público para verificar conectividad
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/health");
            return $response->successful();
        } catch (\Exception $e) {
            /*
            Log::warning("ProductoService: Problemas de conectividad", [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl
            ]);
            */
            return false;
        }
    }
}