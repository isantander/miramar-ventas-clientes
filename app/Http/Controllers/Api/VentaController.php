<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VentaStoreRequest;
use App\Http\Requests\VentaUpdateRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Services\VentaService;
use App\Exceptions\VentaServiceException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use App\Services\ProductoService;


class VentaController extends Controller
{
    private VentaService $ventaService;

    public function __construct(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {

            // query con filtros opcionales
            $query = Venta::with(['cliente', 'detalleVentas'])
                ->orderBy('fecha', 'desc')
                ->orderBy('created_at', 'desc');

            // filtros opcionales
            if ($request->filled('cliente_id')) {
                $query->where('id_cliente', $request->cliente_id);
            }

            if ($request->filled('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }

            if ($request->filled('medio_pago')) {
                $query->where('medio_pago', 'like', '%' . $request->medio_pago . '%');
            }

            // paginación
            $perPage = min($request->get('per_page', 15), 50); // Máximo 50 por página
            $ventas = $query->paginate($perPage);

            return VentaResource::collection($ventas);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno al obtener las ventas',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * POST /api/ventas
     */
    public function store(VentaStoreRequest $request): JsonResponse
    {
        try {

            $data = $request->getValidatedForService();
  
            $venta = $this->ventaService->crearVenta($data);

            return response()->json([
                'message' => 'Venta creada exitosamente',
                'data' => new VentaResource($venta)
            ], 201);

        } catch (VentaServiceException $e) {

            return response()->json([
                'error' => 'Error al procesar la venta',
                'message' => $e->getMessage()
            ], $e->getHttpCode());

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error interno al crear la venta',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * GET /api/ventas/{id}
    */
    public function show(Venta $venta): JsonResponse
    {
        try {
    
            $venta->load(['cliente', 'detalleVentas']);

            return response()->json([
                'data' => new VentaResource($venta)
            ]);

        } catch (\Exception $e) {
    
            return response()->json([
                'error' => 'Error al obtener la venta',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * PUT /api/ventas/{id}
     */
    public function update(VentaUpdateRequest $request, Venta $venta): JsonResponse
    {
        try {

            $data = $request->getValidatedForService();

            // Actualizar usando el servicio
            $ventaActualizada = $this->ventaService->actualizarVenta($venta, $data);

            return response()->json([
                'message' => 'Venta actualizada exitosamente',
                'data' => new VentaResource($ventaActualizada)
            ]);

        } catch (VentaServiceException $e) {

            return response()->json([
                'error' => 'Error al actualizar la venta',
                'message' => $e->getMessage()
            ], $e->getHttpCode());

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error interno al actualizar la venta',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * DELETE /api/ventas/{id}
     */
    public function destroy(Venta $venta): JsonResponse
    {
        try {

            $this->ventaService->eliminarVenta($venta);
            return response()->json([
                'message' => 'Venta eliminada exitosamente'
            ], 204);

        } catch (VentaServiceException $e) {

            return response()->json([
                'error' => 'Error al eliminar la venta',
                'message' => $e->getMessage()
            ], $e->getHttpCode());

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error interno al eliminar la venta',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * GET /api/ventas/estadisticas
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {

            $filtros = $request->only(['fecha_desde', 'fecha_hasta', 'cliente_id']);
            $estadisticas = $this->ventaService->obtenerEstadisticas($filtros);

            return response()->json([
                'data' => $estadisticas,
                'periodo' => [
                    'desde' => $filtros['fecha_desde'] ?? 'Sin límite',
                    'hasta' => $filtros['fecha_hasta'] ?? 'Sin límite'
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Error al obtener estadísticas',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * GET /api/ventas/health
     */
    public function health(ProductoService $productoService): JsonResponse
    {
        try {
            $conectividad = $productoService->verificarConectividad();

            return response()->json([
                'status' => $conectividad ? 'healthy' : 'degraded',
                'productos_service' => $conectividad ? 'connected' : 'disconnected',
                'timestamp' => now()->toISOString()
            ], $conectividad ? 200 : 503);

        } catch (\Exception $e) {
            /*
            Log::error('VentaController: Error verificando conectividad', [
                'error' => $e->getMessage()
            ]);
            */
            
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }
}