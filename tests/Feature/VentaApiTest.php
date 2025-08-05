<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class VentaApiTest extends TestCase
{
    use RefreshDatabase;

    private function crearClienteBase(): Cliente
    {
        return Cliente::create([
            'nombre' => 'Juan Carlos',
            'apellido' => 'Test Cliente',
            'dni' => 12345678,
            'email' => 'juan.test@ejemplo.com'
        ]);
    }

    private function mockProductoService(array $productos = []): void
    {
        $defaultProductos = [
            ['id' => 1, 'costo' => 50000, 'tipo' => 'servicio'],
            ['id' => 2, 'costo' => 75000, 'tipo' => 'paquete']
        ];

        $productosToMock = empty($productos) ? $defaultProductos : $productos;

        foreach ($productosToMock as $producto) {
            Http::fake([
                "*/api/servicios/{$producto['id']}" => Http::response([
                    'data' => [
                        'id' => $producto['id'],
                        'costo' => $producto['costo']
                    ]
                ], 200),
                "*/api/paquetes/{$producto['id']}" => Http::response([
                    'data' => [
                        'id' => $producto['id'],
                        'precio_calculado' => $producto['costo']
                    ]
                ], 200)
            ]);
        }
    }

    /** @test */
    public function puede_crear_venta_con_un_servicio()
    {
        $this->mockProductoService([
            ['id' => 1, 'costo' => 45000, 'tipo' => 'servicio']
        ]);

        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Tarjeta de Crédito',
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'servicio'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id',
                        'fecha',
                        'medio_pago',
                        'costo_total',
                        'cliente',
                        'detalle_ventas'
                    ]
                ]);

        $this->assertDatabaseHas('ventas', [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Tarjeta de crédito',
            'costo_total' => 45000
        ]);

        $this->assertDatabaseHas('detalle_ventas', [
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 45000
        ]);
    }

    /** @test */
    public function puede_crear_venta_con_multiples_productos()
    {
        $this->mockProductoService([
            ['id' => 1, 'costo' => 30000, 'tipo' => 'servicio'],
            ['id' => 2, 'costo' => 72000, 'tipo' => 'paquete']
        ]);

        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'servicio'
                ],
                [
                    'producto_id' => 2,
                    'tipo' => 'paquete'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(201);

        // Costo total: 30000 + 72000 = 102000
        $this->assertDatabaseHas('ventas', [
            'costo_total' => 102000
        ]);

        $venta = Venta::latest()->first();
        $this->assertCount(2, $venta->detalleVentas);
    }

    /** @test */
    public function no_puede_crear_venta_con_cliente_inexistente()
    {
        $ventaData = [
            'id_cliente' => 999, // Cliente que no existe
            'medio_pago' => 'Tarjeta',
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'servicio'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['id_cliente']);
    }

    /** @test */
    public function no_puede_crear_venta_sin_items()
    {
        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [] // Sin items
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['items']);
    }

    /** @test */
    public function valida_fecha_no_puede_ser_futura()
    {
        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => Carbon::tomorrow()->format('Y-m-d'),
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'servicio'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fecha']);
    }

    /** @test */
    public function valida_tipo_de_producto_debe_ser_servicio_o_paquete()
    {
        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'tipo_invalido'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['items.0.tipo']);
    }

    /** @test */
    public function puede_listar_ventas_con_relaciones()
    {
        $cliente = $this->crearClienteBase();
        
        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Tarjeta Débito',
            'id_cliente' => $cliente->id,
            'costo_total' => 85000
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 85000
        ]);

        $response = $this->getJson('/api/ventas');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'fecha',
                            'medio_pago',
                            'costo_total',
                            'cliente' => [
                                'nombre',
                                'apellido',
                                'dni'
                            ],
                            'detalle_ventas'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function puede_obtener_venta_por_id()
    {
        $cliente = $this->crearClienteBase();
        
        $venta = Venta::create([
            'fecha' => Carbon::create(2025, 6, 15),
            'medio_pago' => 'Transferencia',
            'id_cliente' => $cliente->id,
            'costo_total' => 125000
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 2,
            'tipo' => 'paquete',
            'precio_unitario' => 125000
        ]);

        $response = $this->getJson("/api/ventas/{$venta->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $venta->id,
                        'costo_total' => '125000.00'
                    ]
                ]);
    }

    /** @test */
    public function puede_filtrar_ventas_por_cliente()
    {
        $cliente1 = $this->crearClienteBase();
        $cliente2 = Cliente::create([
            'nombre' => 'María',
            'apellido' => 'González',
            'dni' => 87654321,
            'email' => 'maria@test.com'
        ]);

        // Venta para cliente 1
        Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente1->id,
            'costo_total' => 50000
        ]);

        // Venta para cliente 2
        Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Tarjeta',
            'id_cliente' => $cliente2->id,
            'costo_total' => 75000
        ]);

        $response = $this->getJson("/api/ventas?cliente_id={$cliente1->id}");

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function puede_filtrar_ventas_por_rango_de_fechas()
    {
        $cliente = $this->crearClienteBase();

        // Venta dentro del rango
        Venta::create([
            'fecha' => Carbon::create(2025, 6, 15),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        // Venta fuera del rango
        Venta::create([
            'fecha' => Carbon::create(2025, 8, 15),
            'medio_pago' => 'Tarjeta',
            'id_cliente' => $cliente->id,
            'costo_total' => 75000
        ]);

        $response = $this->getJson('/api/ventas?fecha_desde=2025-06-01&fecha_hasta=2025-06-30');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function puede_actualizar_venta_existente()
    {
        $this->mockProductoService([
            ['id' => 3, 'costo' => 95000, 'tipo' => 'servicio']
        ]);

        $cliente = $this->crearClienteBase();
        
        $venta = Venta::create([
            'fecha' => Carbon::create(2025, 6, 15),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        $datosActualizados = [
            'id_cliente' => $cliente->id,
            'fecha' => '2025-06-15',
            'medio_pago' => 'Tarjeta de Débito',
            'items' => [
                [
                    'producto_id' => 3,
                    'tipo' => 'servicio'
                ]
            ]
        ];

        $response = $this->putJson("/api/ventas/{$venta->id}", $datosActualizados);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'medio_pago' => 'Tarjeta de débito',
            'costo_total' => 95000
        ]);
    }

    /** @test */
    public function puede_eliminar_venta_con_soft_delete()
    {
        $cliente = $this->crearClienteBase();
        
        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 60000
        ]);

        $detalle = DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 60000
        ]);

        $response = $this->deleteJson("/api/ventas/{$venta->id}");

        $response->assertStatus(204);

        // Verificar soft delete
        $this->assertSoftDeleted('ventas', ['id' => $venta->id]);
        
        // Verificar que se eliminaron los detalles también
        $this->assertDatabaseMissing('detalle_ventas', [
            'venta_id' => $venta->id
        ]);
    }

    /** @test */
    public function endpoint_estadisticas_funciona_correctamente()
    {
        $cliente = $this->crearClienteBase();

        // Crear múltiples ventas para estadísticas
        Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Tarjeta',
            'id_cliente' => $cliente->id,
            'costo_total' => 75000
        ]);

        $response = $this->getJson('/api/ventas/estadisticas');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'total_ventas',
                        'monto_total',
                        'promedio_venta'
                    ],
                    'periodo'
                ]);
    }

    /** @test */
    public function endpoint_health_verifica_conectividad_con_productos()
    {
        // Mock successful connection
        Http::fake([
            '*/api/health' => Http::response(['status' => 'ok'], 200)
        ]);

        $response = $this->getJson('/api/ventas/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'productos_service',
                    'timestamp'
                ]);
    }

    /** @test */
    public function scope_por_fecha_funciona_correctamente()
    {
        $cliente = $this->crearClienteBase();
        $fechaTest = Carbon::create(2025, 6, 15);

        $venta = Venta::create([
            'fecha' => $fechaTest,
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        $ventasEncontradas = Venta::porFecha($fechaTest->format('Y-m-d'))->get();

        $this->assertCount(1, $ventasEncontradas);
        $this->assertEquals($venta->id, $ventasEncontradas->first()->id);
    }

    /** @test */
    public function scope_por_cliente_funciona_correctamente()
    {
        $cliente = $this->crearClienteBase();

        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        $ventasEncontradas = Venta::porCliente($cliente->id)->get();

        $this->assertCount(1, $ventasEncontradas);
        $this->assertEquals($venta->id, $ventasEncontradas->first()->id);
    }

    /** @test */
    public function detalle_venta_scopes_funcionan_correctamente()
    {
        $cliente = $this->crearClienteBase();
        
        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 125000
        ]);

        // Crear detalles de diferentes tipos
        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 50000
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 2,
            'tipo' => 'paquete',
            'precio_unitario' => 75000
        ]);

        // Probar scope servicios
        $servicios = DetalleVenta::servicios()->get();
        $this->assertCount(1, $servicios);

        // Probar scope paquetes
        $paquetes = DetalleVenta::paquetes()->get();
        $this->assertCount(1, $paquetes);

        // Probar scope por tipo
        $serviciosPorTipo = DetalleVenta::porTipo('servicio')->get();
        $this->assertCount(1, $serviciosPorTipo);
    }

    /** @test */
    public function maneja_error_cuando_producto_service_no_disponible()
    {
        // Mock failed connection to products service
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'servicio'
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        // Debe manejar graciosamente el error de conectividad
        $response->assertStatus(400);
    }

    /** @test */
    public function normaliza_medio_pago_correctamente()
    {
        $this->mockProductoService([
            ['id' => 1, 'costo' => 50000, 'tipo' => 'servicio']
        ]);

        $cliente = $this->crearClienteBase();

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => '  TARJETA DE CREDITO  ', // Con espacios y mayúsculas
            'fecha' => '2025-06-15',
            'items' => [
                [
                    'producto_id' => 1,
                    'tipo' => 'SERVICIO' // También con mayúsculas
                ]
            ]
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ventas', [
            'medio_pago' => 'Tarjeta de credito' // Normalizado
        ]);

        $this->assertDatabaseHas('detalle_ventas', [
            'tipo' => 'servicio' // Normalizado a minúsculas
        ]);
    }

    /** @test */
    public function retorna_404_para_venta_inexistente()
    {
        $response = $this->getJson('/api/ventas/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function valida_campos_requeridos_en_creacion()
    {
        $response = $this->postJson('/api/ventas', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'id_cliente',
                    'medio_pago',
                    'fecha',
                    'items'
                ]);
    }

    /** @test */
    public function valida_maximo_50_items_por_venta()
    {
        $cliente = $this->crearClienteBase();

        // Crear array con 51 items
        $items = [];
        for ($i = 1; $i <= 51; $i++) {
            $items[] = [
                'producto_id' => $i,
                'tipo' => 'servicio'
            ];
        }

        $ventaData = [
            'id_cliente' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => $items
        ];

        $response = $this->postJson('/api/ventas', $ventaData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['items']);
    }
}