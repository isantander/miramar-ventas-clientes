<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$allowedTypes): Response
    {
        // Obtener API key del header
        $apiKeyValue = $request->header('X-API-Key');
        
        if (!$apiKeyValue) {
            return response()->json([
                'error' => 'API key requerida',
                'message' => 'Debe proporcionar una API key válida en el header X-API-Key'
            ], 401);
        }

        // Validar la API key
        $apiKey = ApiKey::validateKey($apiKeyValue);
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key inválida',
                'message' => 'La API key proporcionada no es válida o está inactiva'
            ], 401);
        }

        // Verificar tipo de API key si se especificó
        if (!empty($allowedTypes) && !in_array($apiKey->type, $allowedTypes)) {
            return response()->json([
                'error' => 'Acceso denegado',
                'message' => 'Esta API key no tiene permisos para acceder a este endpoint'
            ], 403);
        }

        // Verificar acceso al endpoint específico
        $endpoint = $request->getPathInfo();
        if (!$apiKey->hasAccessToEndpoint($endpoint)) {
            return response()->json([
                'error' => 'Endpoint no permitido',
                'message' => "Esta API key no tiene acceso al endpoint: {$endpoint}"
            ], 403);
        }

        // Verificar rate limiting
        if ($apiKey->hasExceededRateLimit()) {
            return response()->json([
                'error' => 'Rate limit excedido',
                'message' => "Ha superado el límite de {$apiKey->rate_limit_per_minute} requests por minuto",
                'retry_after' => 60
            ], 429);
        }

        // Incrementar contadores
        $apiKey->incrementRateLimit();
        $apiKey->recordUsage();

        // Agregar información de la API key al request
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_type', $apiKey->type);
        $request->attributes->set('api_key_name', $apiKey->name);

        return $next($request);
    }
}