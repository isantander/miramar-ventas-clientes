<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Nombre descriptivo de la API Key');
            $table->string('key', 64)->unique()->comment('API Key hasheada');
            $table->string('key_prefix', 10)->comment('Prefijo visible (ej: ak_1234...)');
            $table->enum('type', ['frontend', 'internal', 'admin'])->default('frontend');
            $table->integer('rate_limit_per_minute')->default(60)->comment('Límite de requests por minuto');
            $table->json('allowed_endpoints')->nullable()->comment('Endpoints permitidos (null = todos)');
            $table->json('metadata')->nullable()->comment('Metadata adicional (cliente, versión, etc.)');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('total_requests')->default(0)->comment('Contador total de requests');
            $table->timestamps();

            // Índices
            $table->index(['key', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};