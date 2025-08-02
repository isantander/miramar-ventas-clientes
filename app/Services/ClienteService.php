<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    public function listarTodos(): Collection
    {
        return Cliente::all();
    }

    public function obtenerPorId(int $id): ?Cliente
    {
        return Cliente::find($id);
    }

    public function crear(array $datos): Cliente
    {
        return Cliente::create($datos);
    }

    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        $cliente->update($datos);
        return $cliente->fresh();
    }

    public function eliminar(Cliente $cliente): bool
    {
        return $cliente->delete();
    }

    public function buscarPorDni(int $dni): ?Cliente
    {
        return Cliente::porDni($dni)->first();
    }

    public function buscarPorEmail(string $email): ?Cliente
    {
        return Cliente::where('email', $email)->first();
    }
}