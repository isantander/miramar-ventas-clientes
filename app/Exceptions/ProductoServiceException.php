<?php

namespace App\Exceptions;

use Exception;

class ProductoServiceException extends Exception
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
            'error' => 'Error en comunicación con productos',
            'message' => $this->getMessage(),
            'code' => $this->httpCode
        ], $this->httpCode);
    }
}