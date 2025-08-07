<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\VentaController;

/*
|--------------------------------------------------------------------------
| API Routes - Microservicio Ventas-Clientes
|--------------------------------------------------------------------------
*/

// Rutas públicas con autenticación por API Key
Route::middleware(['api.key:frontend,admin'])->group(function () {
    
    // Gestión de Clientes
    Route::apiResource('clientes', ClienteController::class);
    
    // Rutas adicionales de Ventas ANTES del resource (orden importante)
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::get('estadisticas', [VentaController::class, 'estadisticas'])->name('estadisticas');
        Route::get('health', [VentaController::class, 'health'])->name('health');
    });
    
    // Gestión de Ventas - Resource routes AL FINAL
    Route::apiResource('ventas', VentaController::class);
});

// Rutas internas (comunicación entre microservicios) 
Route::middleware(['internal.service'])->prefix('internal')->group(function () {
    // Endpoints optimizados para comunicación interna
    Route::get('clientes/{id}', [ClienteController::class, 'show'])->name('internal.clientes.show');
    Route::get('ventas/{id}', [VentaController::class, 'show'])->name('internal.ventas.show');
    
    // Validación de clientes para otros servicios
    Route::post('clientes/validate', function(\Illuminate\Http\Request $request) {
        $clienteIds = $request->validate([
            'cliente_ids' => 'required|array',
            'cliente_ids.*' => 'integer'
        ]);

        $results = [];
        foreach ($clienteIds['cliente_ids'] as $id) {
            try {
                $controller = new ClienteController(app(\App\Services\ClienteService::class));
                $cliente = $controller->show($id);
                
                $results[] = [
                    'cliente_id' => $id,
                    'found' => true,
                    'data' => $cliente->getData(true)
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'cliente_id' => $id,
                    'found' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json(['results' => $results]);
    })->name('internal.clientes.validate');
});

/*
|--------------------------------------------------------------------------
| Health Check del Microservicio
|--------------------------------------------------------------------------
*/

// Health check y documentación sin autenticación
Route::get('health', function () {
    return response()->json([
        'service' => 'miramar-ventas-clientes',
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'authentication' => 'api-key'
    ]);
})->name('health');

// Health check específico de comunicación con productos (sin auth)
Route::get('ventas/health', [VentaController::class, 'health'])->name('ventas.health.public');

Route::get('info', function () {
    return response()->json([
        'service' => 'miramar-ventas-clientes',
        'description' => 'Microservicio para gestión de clientes y ventas',
        'version' => '1.0.0',
        'authentication' => [
            'type' => 'api-key',
            'header' => 'X-API-Key',
            'types' => ['frontend', 'admin']
        ],
        'endpoints' => [
            'public' => [
                'GET /api/health' => 'Health check',
                'GET /api/info' => 'API information'
            ],
            'authenticated' => [
                'clientes' => [
                    'GET /api/clientes' => 'Listar clientes',
                    'POST /api/clientes' => 'Crear cliente',
                    'GET /api/clientes/{id}' => 'Mostrar cliente',
                    'PUT /api/clientes/{id}' => 'Actualizar cliente',
                    'DELETE /api/clientes/{id}' => 'Eliminar cliente'
                ],
                'ventas' => [
                    'GET /api/ventas' => 'Listar ventas (con filtros)',
                    'POST /api/ventas' => 'Crear venta (comunica con productos)',
                    'GET /api/ventas/{id}' => 'Mostrar venta',
                    'PUT /api/ventas/{id}' => 'Actualizar venta',
                    'DELETE /api/ventas/{id}' => 'Eliminar venta',
                    'GET /api/ventas/estadisticas' => 'Estadísticas de ventas',
                    'GET /api/ventas/health' => 'Estado comunicación con productos'
                ]
            ],
            'internal' => [
                'GET /api/internal/clientes/{id}' => 'Cliente para comunicación interna',
                'POST /api/internal/clientes/validate' => 'Validar múltiples clientes'
            ]
        ],
        'dependencies' => [
            'miramar-productos' => config('services.productos.url')
        ]
    ]);
})->name('info');