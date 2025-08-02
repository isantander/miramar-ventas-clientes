<?php

namespace App\Exceptions;

use Exception;

class VentaServiceException extends Exception
{
    private int $httpCode;

    public function __construct(string $message = "", int $httpCode = 500, \Throwable $previous = null)
    {
        $this->httpCode = $httpCode;
        parent::__construct($message, $httpCode, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function render()
    {
        return response()->json([
            'error' => 'Error en el procesamiento de venta',
            'message' => $this->getMessage(),
            'code' => $this->httpCode,
            'timestamp' => now()->toISOString()
        ], $this->httpCode);
    }
}