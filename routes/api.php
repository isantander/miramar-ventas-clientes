<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\VentaController;


/*
* Microservicio miramar-ventas-clientes  Puerto: 8020
*/

// rutas de Clientes
Route::apiResource('clientes', ClienteController::class);


// Rutas de Ventas (con rutas adicionales)
Route::prefix('ventas')->name('ventas.')->group(function () {
    // Rutas adicionales ANTES del resource (orden importante)
    Route::get('estadisticas', [VentaController::class, 'estadisticas'])->name('estadisticas');
    Route::get('health', [VentaController::class, 'health'])->name('health');
});

// Resource routes de ventas
Route::apiResource('ventas', VentaController::class);

/*
|--------------------------------------------------------------------------
| Health Check del Microservicio
|--------------------------------------------------------------------------
*/

Route::get('health', function () {
    return response()->json([
        'service' => 'miramar-ventas-clientes',
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'port' => 8020
    ]);
})->name('health.general');

/*
|--------------------------------------------------------------------------
| Información del Microservicio
|--------------------------------------------------------------------------
*/

Route::get('info', function () {
    return response()->json([
        'service' => 'miramar-ventas-clientes',
        'description' => 'Microservicio para gestión de clientes y ventas',
        'version' => '1.0.0',
        'endpoints' => [
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
        'dependencies' => [
            'miramar-productos' => config('services.productos.url')
        ]
    ]);
})->name('info');