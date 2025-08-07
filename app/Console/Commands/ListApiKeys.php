<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ListApiKeys extends Command
{
    protected $signature = 'api:list-keys 
                          {--type= : Filtrar por tipo (frontend, internal, admin)}
                          {--status= : Filtrar por estado (active, inactive)}
                          {--recent= : Solo keys usadas en los últimos N días}';

    protected $description = 'Lista todas las API Keys del sistema';

    public function handle(): int
    {
        $query = ApiKey::query()->orderBy('created_at', 'desc');

        // Aplicar filtros
        if ($type = $this->option('type')) {
            if (!in_array($type, ['frontend', 'internal', 'admin'])) {
                $this->error("Tipo inválido. Use: frontend, internal, admin");
                return 1;
            }
            $query->byType($type);
        }

        if ($status = $this->option('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } else {
                $this->error("Estado inválido. Use: active, inactive");
                return 1;
            }
        }

        if ($recent = $this->option('recent')) {
            if (!is_numeric($recent)) {
                $this->error("El parámetro 'recent' debe ser un número");
                return 1;
            }
            $query->recentlyUsed((int) $recent);
        }

        $apiKeys = $query->get();

        if ($apiKeys->isEmpty()) {
            $this->info("No se encontraron API Keys con los criterios especificados.");
            return 0;
        }

        $this->info("API Keys encontradas: " . $apiKeys->count());
        $this->newLine();

        // Preparar datos para la tabla
        $tableData = [];
        foreach ($apiKeys as $key) {
            $tableData[] = [
                'ID' => $key->id,
                'Nombre' => $key->name,
                'Tipo' => $this->colorizeType($key->type),
                'Estado' => $key->is_active ? '<fg=green>✅ Activa</>' : '<fg=red>❌ Inactiva</>',
                'Rate Limit' => $key->rate_limit_per_minute . '/min',
                'Requests' => number_format($key->total_requests),
                'Último Uso' => $key->last_used_at ? $key->last_used_at->diffForHumans() : '<fg=gray>Nunca</>',
                'Key' => $key->masked_key,
                'Creada' => $key->created_at->format('d/m/Y'),
            ];
        }

        $this->table([
            'ID', 'Nombre', 'Tipo', 'Estado', 'Rate Limit', 
            'Requests', 'Último Uso', 'Key', 'Creada'
        ], $tableData);

        // Estadísticas
        $this->newLine();
        $this->showStatistics($apiKeys);

        return 0;
    }

    private function colorizeType(string $type): string
    {
        return match($type) {
            'frontend' => '<fg=blue>' . $type . '</>',
            'internal' => '<fg=yellow>' . $type . '</>',
            'admin' => '<fg=red>' . $type . '</>',
            default => $type
        };
    }

    private function showStatistics($apiKeys): void
    {
        $stats = $apiKeys->groupBy('type')->map->count();
        $active = $apiKeys->where('is_active', true)->count();
        $inactive = $apiKeys->where('is_active', false)->count();
        $totalRequests = $apiKeys->sum('total_requests');

        $this->line("<options=bold>Estadísticas:</>");
        $this->table(['Métrica', 'Valor'], [
            ['Total API Keys', $apiKeys->count()],
            ['Activas', "<fg=green>{$active}</>"],
            ['Inactivas', "<fg=red>{$inactive}</>"],
            ['Frontend', $stats->get('frontend', 0)],
            ['Internal', $stats->get('internal', 0)],
            ['Admin', $stats->get('admin', 0)],
            ['Total Requests', "<fg=cyan>" . number_format($totalRequests) . "</>"],
        ]);
    }
}