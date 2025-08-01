<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('medio_pago'); // texto libre según consigna
            $table->foreignId('id_cliente')->constrained('clientes')->onDelete('restrict');
            $table->decimal('costo_total', 10, 2); // es calculado desde el microservicio de productos
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('fecha');
            $table->index('id_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
