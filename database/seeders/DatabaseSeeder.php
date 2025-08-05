<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Primero crear clientes, luego ventas (que dependen de clientes)
        $this->call([
            ClienteSeeder::class,
            VentaSeeder::class,
        ]);

        $this->command->info('✅ Microservicio de ventas-clientes poblado exitosamente');
        $this->command->info('👥 Clientes creados: 3 (María Elena, Carlos Eduardo, Ana Sofía)');
        $this->command->info('🛒 Ventas creadas: 4 (con comunicación al microservicio de productos)');
    }
}
