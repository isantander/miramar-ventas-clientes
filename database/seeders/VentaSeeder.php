<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Services\ProductoService;
use Carbon\Carbon;

class VentaSeeder extends Seeder
{
    private ProductoService $productoService;

    public function __construct()
    {
        $this->productoService = new ProductoService();
    }

    public function run(): void
    {
        // VENTA 1: María Elena compra Paquete Bariloche VIP
        $clienteMaria = Cliente::where('email', 'maria.rodriguez@gmail.com')->first();
        
        $ventaMaria = Venta::create([
            'fecha' => Carbon::create(2025, 2, 15),
            'medio_pago' => 'Tarjeta de Crédito Visa',
            'id_cliente' => $clienteMaria->id,
            'costo_total' => 0 // Se calculará después
        ]);

        // Detalles: Paquete Bariloche VIP (ID aproximado 1)
        $paqueteBarilocheVip = $this->obtenerPrecioProducto(1, 'paquete');
        
        DetalleVenta::create([
            'venta_id' => $ventaMaria->id,
            'producto_id' => 1,
            'tipo' => 'paquete',
            'precio_unitario' => $paqueteBarilocheVip['precio']
        ]);

        // Actualizar costo total
        $ventaMaria->update(['costo_total' => $paqueteBarilocheVip['precio']]);

        // VENTA 2: Carlos compra servicios individuales (Hotel + Excursión Calafate)
        $clienteCarlos = Cliente::where('email', 'carlos.mendoza@hotmail.com')->first();
        
        $ventaCarlos = Venta::create([
            'fecha' => Carbon::create(2025, 3, 8),
            'medio_pago' => 'Transferencia Bancaria',
            'id_cliente' => $clienteCarlos->id,
            'costo_total' => 0
        ]);

        // Servicios individuales
        $hotelCalafate = $this->obtenerPrecioProducto(8, 'servicio'); // HTL-CAL01
        $excursionCalafate = $this->obtenerPrecioProducto(11, 'servicio'); // EXC-CAL01
        
        DetalleVenta::create([
            'venta_id' => $ventaCarlos->id,
            'producto_id' => 8,
            'tipo' => 'servicio',
            'precio_unitario' => $hotelCalafate['precio']
        ]);

        DetalleVenta::create([
            'venta_id' => $ventaCarlos->id,
            'producto_id' => 11,
            'tipo' => 'servicio', 
            'precio_unitario' => $excursionCalafate['precio']
        ]);

        $ventaCarlos->update([
            'costo_total' => $hotelCalafate['precio'] + $excursionCalafate['precio']
        ]);

        // VENTA 3: Ana Sofía compra Paquete San Martín Económico + Traslado Bariloche extra
        $clienteAna = Cliente::where('email', 'ana.fernandez@yahoo.com.ar')->first();
        
        $ventaAna = Venta::create([
            'fecha' => Carbon::create(2025, 4, 12),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $clienteAna->id,
            'costo_total' => 0
        ]);

        $paqueteSmaEco = $this->obtenerPrecioProducto(4, 'paquete'); // PAQ-SMA-ECO
        $trasladoBariloche = $this->obtenerPrecioProducto(1, 'servicio'); // TRA-BRC01

        DetalleVenta::create([
            'venta_id' => $ventaAna->id,
            'producto_id' => 4,
            'tipo' => 'paquete',
            'precio_unitario' => $paqueteSmaEco['precio']
        ]);

        DetalleVenta::create([
            'venta_id' => $ventaAna->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => $trasladoBariloche['precio']
        ]);

        $ventaAna->update([
            'costo_total' => $paqueteSmaEco['precio'] + $trasladoBariloche['precio']
        ]);

        // VENTA 4: María Elena hace segunda compra - Paquete Patagonia Completa
        $ventaMariaSegunda = Venta::create([
            'fecha' => Carbon::create(2025, 6, 20),
            'medio_pago' => 'Tarjeta de Débito Mastercard',
            'id_cliente' => $clienteMaria->id,
            'costo_total' => 0
        ]);

        $paquetePatagoniaCompleta = $this->obtenerPrecioProducto(7, 'paquete'); // PAQ-PAT-FULL
        
        DetalleVenta::create([
            'venta_id' => $ventaMariaSegunda->id,
            'producto_id' => 7,
            'tipo' => 'paquete',
            'precio_unitario' => $paquetePatagoniaCompleta['precio']
        ]);

        $ventaMariaSegunda->update(['costo_total' => $paquetePatagoniaCompleta['precio']]);
    }

    /**
     * Obtiene el precio de un producto desde el microservicio de productos
     * En caso de error, usa precios estimados para el seeder
     */
    private function obtenerPrecioProducto(int $productoId, string $tipo): array
    {
        try {
            return $this->productoService->obtenerProducto($productoId, $tipo);
        } catch (\Exception $e) {
            // Precios estimados en caso de que el microservicio no esté disponible
            $preciosEstimados = [
                // Servicios
                1 => ['precio' => 8500, 'tipo' => 'servicio'],   // Traslado Bariloche
                2 => ['precio' => 12000, 'tipo' => 'servicio'],  // Traslado SMA
                3 => ['precio' => 7800, 'tipo' => 'servicio'],   // Traslado Calafate
                4 => ['precio' => 185000, 'tipo' => 'servicio'], // Hotel Llao Llao
                5 => ['precio' => 89000, 'tipo' => 'servicio'],  // Hotel Austral
                6 => ['precio' => 167000, 'tipo' => 'servicio'], // Hotel Posada Marisol
                7 => ['precio' => 78000, 'tipo' => 'servicio'],  // Hotel Patagónico
                8 => ['precio' => 142000, 'tipo' => 'servicio'], // Hotel Los Alamos
                9 => ['precio' => 96000, 'tipo' => 'servicio'],  // Hotel Mirador
                10 => ['precio' => 45000, 'tipo' => 'servicio'], // Excursión Bariloche
                11 => ['precio' => 67000, 'tipo' => 'servicio'], // Excursión Calafate
                
                // Paquetes (con descuento del 10%)
                1 => ['precio' => 208350, 'tipo' => 'paquete'], // Bariloche VIP
                2 => ['precio' => 127350, 'tipo' => 'paquete'], // Bariloche ECO
                3 => ['precio' => 207900, 'tipo' => 'paquete'], // SMA VIP
                4 => ['precio' => 131400, 'tipo' => 'paquete'], // SMA ECO
                5 => ['precio' => 189720, 'tipo' => 'paquete'], // Calafate VIP
                6 => ['precio' => 145620, 'tipo' => 'paquete'], // Calafate ECO
                7 => ['precio' => 462150, 'tipo' => 'paquete'], // Patagonia Completa
            ];

            return $preciosEstimados[$productoId] ?? ['precio' => 50000, 'tipo' => $tipo];
        }
    }
}