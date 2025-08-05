<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cliente;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = [
            [
                'nombre' => 'María Elena',
                'apellido' => 'Rodríguez Sánchez',
                'dni' => 32456789,
                'email' => 'maria.rodriguez@gmail.com'
            ],
            [
                'nombre' => 'Carlos Eduardo',
                'apellido' => 'Mendoza López',
                'dni' => 28934567,
                'email' => 'carlos.mendoza@hotmail.com'
            ],
            [
                'nombre' => 'Ana Sofía',
                'apellido' => 'Fernández Castro',
                'dni' => 35678901,
                'email' => 'ana.fernandez@yahoo.com.ar'
            ]
        ];

        foreach ($clientes as $cliente) {
            Cliente::create($cliente);
        }
    }
}