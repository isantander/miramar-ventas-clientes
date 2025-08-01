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
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->integer('producto_id'); // id del servicio / paquete en el otro microservicio
            $table->enum('tipo', ['servicio', 'paquete']); // enum para saber a cuál de los endpoints debo consultar
            $table->decimal('precio_unitario', 10, 2);
            $table->timestamps();
            
            $table->index('venta_id');
            $table->index(['producto_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
