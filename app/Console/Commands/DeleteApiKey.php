<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class DeleteApiKey extends Command
{
    protected $signature = 'api:delete-key 
                          {id : ID de la API Key a eliminar}
                          {--force : Forzar eliminación sin confirmación}';

    protected $description = 'Elimina una API Key del sistema (IRREVERSIBLE)';

    public function handle(): int
    {
        $id = $this->argument('id');
        $force = $this->option('force');

        // Buscar API Key
        $apiKey = ApiKey::find($id);
        if (!$apiKey) {
            $this->error("❌ API Key con ID {$id} no encontrada");
            return 1;
        }

        // Mostrar información de la key a eliminar
        $this->showApiKeyToDelete($apiKey);

        // Confirmaciones de seguridad
        if (!$force && !$this->confirmDeletion($apiKey)) {
            $this->info("Operación cancelada");
            return 0;
        }

        // Guardar información antes de eliminar
        $keyInfo = [
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'type' => $apiKey->type,
            'total_requests' => $apiKey->total_requests,
            'masked_key' => $apiKey->masked_key
        ];

        // Eliminar
        $apiKey->delete();

        $this->info("API Key eliminada exitosamente");
        $this->newLine();
        
        $this->table(['Campo', 'Valor'], [
            ['ID Eliminado', $keyInfo['id']],
            ['Nombre', $keyInfo['name']],
            ['Tipo', $keyInfo['type']],
            ['Total Requests', number_format($keyInfo['total_requests'])],
            ['Key', $keyInfo['masked_key']],
            ['Eliminada', now()->format('d/m/Y H:i:s')],
        ]);

        if ($keyInfo['type'] === 'internal') {
            $this->error("IMPORTANTE: Esta era una API Key INTERNA. Verifique que los servicios tengan keys alternativas.");
        }

        return 0;
    }

    private function showApiKeyToDelete(ApiKey $apiKey): void
    {
        $this->warn("Está a punto de ELIMINAR la siguiente API Key:");
        $this->newLine();

        $status = $apiKey->is_active ? '<fg=green>✅ Activa</>' : '<fg=red>❌ Inactiva</>';
        $type = match($apiKey->type) {
            'frontend' => '<fg=blue>Frontend</>',
            'internal' => '<fg=yellow>Internal</>',
            'admin' => '<fg=red>Admin</>',
            default => $apiKey->type
        };

        $this->table(['Campo', 'Valor'], [
            ['ID', $apiKey->id],
            ['Nombre', $apiKey->name],
            ['Tipo', $type],
            ['Estado', $status],
            ['Key', $apiKey->masked_key],
            ['Total Requests', number_format($apiKey->total_requests)],
            ['Último Uso', $apiKey->last_used_at ? $apiKey->last_used_at->diffForHumans() : 'Nunca'],
            ['Creada', $apiKey->created_at->format('d/m/Y H:i:s')],
        ]);

        $this->newLine();
    }

    private function confirmDeletion(ApiKey $apiKey): bool
    {
        // Advertencias específicas por tipo
        if ($apiKey->type === 'internal') {
            $this->error("ADVERTENCIA: Esta es una API Key INTERNA");
            $this->error("   La eliminación puede afectar la comunicación entre microservicios");
            $this->newLine();
            
            if (!$this->confirm('¿Está COMPLETAMENTE SEGURO de eliminar esta API Key INTERNA?')) {
                return false;
            }
        }

        if ($apiKey->is_active) {
            $this->warn("Esta API Key está ACTIVA y puede estar en uso");
            $this->newLine();
        }

        if ($apiKey->total_requests > 0) {
            $this->info("Esta API Key ha procesado " . number_format($apiKey->total_requests) . " requests");
            $this->newLine();
        }

        // Confirmación final
        $this->error("❌ ESTA ACCIÓN ES IRREVERSIBLE");
        $this->line("Una vez eliminada, la API Key no podrá ser recuperada");
        $this->newLine();

        if (!$this->confirm("¿Confirma que desea ELIMINAR la API Key '{$apiKey->name}'?")) {
            return false;
        }

        // Confirmación extra para keys críticas
        if ($apiKey->type === 'internal' || $apiKey->total_requests > 1000) {
            $confirmText = strtoupper(substr($apiKey->name, 0, 8));
            $userInput = $this->ask("Para confirmar, escriba '{$confirmText}' (las primeras 8 letras del nombre en MAYÚSCULAS)");
            
            if ($userInput !== $confirmText) {
                $this->error("❌ Confirmación incorrecta. Operación cancelada.");
                return false;
            }
        }

        return true;
    }
}