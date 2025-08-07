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
    
    // Gestión de Ventas - Resource routes AL FINAL !!!
    Route::apiResource('ventas', VentaController::class);
});

// Rutas internas (comunicación entre microservicios) 
Route::middleware(['internal.service'])->prefix('internal')->group(function () {
    // Endpoints optimizados para comunicación interna
    Route::get('clientes/{id}', [ClienteController::class, 'show'])->name('internal.clientes.show');
    Route::get('ventas/{id}', [VentaController::class, 'show'])->name('internal.ventas.show');
});


// Health check sin autenticación
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

