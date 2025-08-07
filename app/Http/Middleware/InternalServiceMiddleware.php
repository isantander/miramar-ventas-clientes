<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalServiceMiddleware
{
    /**
     * Handle an incoming request para comunicación entre microservicios
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Token de servicio interno desde header
        $serviceToken = $request->header('X-Service-Token');
        
        if (!$serviceToken) {
            return response()->json([
                'error' => 'Token de servicio requerido',
                'message' => 'Debe proporcionar un token de servicio válido en el header X-Service-Token'
            ], 401);
        }

        // Verificar token contra configuración
        $validTokens = $this->getValidInternalTokens();
        
        if (!in_array($serviceToken, $validTokens)) {
            return response()->json([
                'error' => 'Token de servicio inválido',
                'message' => 'El token de servicio proporcionado no es válido'
            ], 401);
        }

        // Verificar que la IP/origen sea válido (opcional)
        if (!$this->isValidInternalOrigin($request)) {
            return response()->json([
                'error' => 'Origen no autorizado',
                'message' => 'El origen de la request no está autorizado para comunicación interna'
            ], 403);
        }

        // Agregar información del servicio al request
        $serviceName = $this->getServiceNameByToken($serviceToken);
        $request->attributes->set('internal_service', $serviceName);
        $request->attributes->set('is_internal_request', true);

        return $next($request);
    }

    /**
     * Obtiene tokens válidos para servicios internos
     */
    private function getValidInternalTokens(): array
    {
        return [
            config('services.internal.productos_token', 'productos_internal_secret_token_2025'),
            config('services.internal.ventas_token', 'ventas_internal_secret_token_2025'),
            config('services.internal.gateway_token', 'gateway_internal_secret_token_2025'),
        ];
    }

    /**
     * Verifica si el origen es válido para comunicación interna
     */
    private function isValidInternalOrigin(Request $request): bool
    {
        // En Docker, verificamos que venga de la red interna
        $clientIp = $request->ip();
        
        // Rangos de IP de Docker y localhost
        $allowedRanges = [
            '127.0.0.1',
            '::1',
            '172.16.0.0/12',  // Docker default range
            '10.0.0.0/8',     // Docker custom networks
            '192.168.0.0/16', // Local networks
        ];

        // En desarrollo, permitir localhost
        if (app()->environment(['local', 'development'])) {
            return true;
        }

        // Verificar rangos de IP
        foreach ($allowedRanges as $range) {
            if ($this->ipInRange($clientIp, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si una IP está dentro de un rango CIDR
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if ($ip === $range) {
            return true;
        }

        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            return ($ip & $mask) == $subnet;
        }

        return false;
    }

    /**
     * Obtiene el nombre del servicio por token
     */
    private function getServiceNameByToken(string $token): string
    {
        $tokenMap = [
            config('services.internal.productos_token', 'productos_internal_secret_token_2025') => 'productos',
            config('services.internal.ventas_token', 'ventas_internal_secret_token_2025') => 'ventas',
            config('services.internal.gateway_token', 'gateway_internal_secret_token_2025') => 'gateway',
        ];

        return $tokenMap[$token] ?? 'unknown';
    }
}