<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteStoreRequest;
use App\Http\Requests\ClienteUpdateRequest;
use App\Http\Resources\ClienteResource;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    public function __construct(
        private ClienteService $clienteService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $clientes = $this->clienteService->listarTodos();
        return ClienteResource::collection($clientes);

    }

    public function store(ClienteStoreRequest $request): ClienteResource
    {
        $cliente = $this->clienteService->crear($request->validated());
        return new ClienteResource($cliente);        
    }

    public function show(string $id): ClienteResource|JsonResponse
    {
        $cliente = $this->clienteService->obtenerPorId($id);
        
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }
        
        return new ClienteResource($cliente);
    }

    public function update(ClienteUpdateRequest $request, string $id): ClienteResource|JsonResponse
    {
        $cliente = $this->clienteService->obtenerPorId($id);
        
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }
        
        $clienteActualizado = $this->clienteService->actualizar($cliente, $request->validated());
        return new ClienteResource($clienteActualizado);
    }

    public function destroy(string $id): JsonResponse
    {
        $cliente = $this->clienteService->obtenerPorId($id);
        
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }
        
        $this->clienteService->eliminar($cliente);
        return response()->json(null, 204);
    }
}
