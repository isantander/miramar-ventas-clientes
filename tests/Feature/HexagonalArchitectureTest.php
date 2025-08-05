<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\VentaService;
use App\Services\ProductoService;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Exceptions\VentaServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HexagonalArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $ventaService;
    private ProductoService $productoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productoService = new ProductoService();
        $this->ventaService = new VentaService($this->productoService);
    }

    /** @test */
    public function service_layer_encapsula_logica_de_negocio_correctamente()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 50000
                ]
            ], 200)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        // El service debe encapsular toda la lógica de negocio
        $venta = $this->ventaService->crearVenta($data);

        $this->assertInstanceOf(Venta::class, $venta);
        $this->assertEquals(50000, $venta->costo_total);
        $this->assertCount(1, $venta->detalleVentas);
    }

    /** @test */
    public function service_layer_maneja_transacciones_atomicas()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 50000
                ]
            ], 200),
            '*/api/servicios/2' => Http::response([
                'error' => 'Servicio no encontrado'
            ], 404) // Segundo producto falla
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio'],
                ['producto_id' => 2, 'tipo' => 'servicio'] // Este falla
            ]
        ];

        $this->expectException(VentaServiceException::class);

        $this->ventaService->crearVenta($data);

        // Verificar que no se creó ningún registro por rollback de transacción
        $this->assertDatabaseEmpty('ventas');
        $this->assertDatabaseEmpty('detalle_ventas');
    }

    /** @test */
    public function service_layer_valida_reglas_de_negocio()
    {
        // Cliente inexistente debe fallar en el service
        $data = [
            'cliente_id' => 999, // No existe
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        $this->expectException(VentaServiceException::class);
        $this->expectExceptionMessage('Cliente con ID 999 no encontrado');

        $this->ventaService->crearVenta($data);
    }

    /** @test */
    public function service_layer_valida_precios_de_productos()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 0 // Precio inválido
                ]
            ], 200)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        $this->expectException(VentaServiceException::class);
        $this->expectExceptionMessage('tiene precio inválido');

        $this->ventaService->crearVenta($data);
    }

    /** @test */
    public function service_layer_integra_correctamente_con_repositorios()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 50000
                ]
            ], 200)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        $venta = $this->ventaService->crearVenta($data);

        // El service debe cargar las relaciones correctamente
        $this->assertTrue($venta->relationLoaded('cliente'));
        $this->assertTrue($venta->relationLoaded('detalleVentas'));
        $this->assertEquals('Juan', $venta->cliente->nombre);
    }

    /** @test */
    public function service_layer_separa_concerns_correctamente()
    {
        // Mock para simular fallo de comunicación con productos
        Http::fake([
            '*' => function () {
                throw new \Exception('Network error');
            }
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        $this->expectException(VentaServiceException::class);
        $this->expectExceptionMessage('Error interno al procesar la venta');

        $this->ventaService->crearVenta($data);
    }

    /** @test */
    public function service_actualizar_mantiene_atomicidad()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1, 
                    'nombre' => 'Hotel Original',
                    'costo' => 50000
                ]
            ], 200),
            '*/api/servicios/2' => Http::response([
                'data' => [
                    'id' => 2,
                    'nombre' => 'Hotel Actualizado', 
                    'costo' => 75000
                ]
            ], 200)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        // Crear venta inicial
        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 50000
        ]);

        // Actualizar venta
        $dataActualizacion = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Tarjeta',
            'items' => [
                ['producto_id' => 2, 'tipo' => 'servicio']
            ]
        ];

        $ventaActualizada = $this->ventaService->actualizarVenta($venta, $dataActualizacion);

        // Verificar que se actualizó correctamente
        $this->assertEquals(75000, $ventaActualizada->costo_total);
        $this->assertEquals('Tarjeta', $ventaActualizada->medio_pago);
        $this->assertCount(1, $ventaActualizada->detalleVentas);
        $this->assertEquals(2, $ventaActualizada->detalleVentas->first()->producto_id);
    }

    /** @test */
    public function service_eliminar_mantiene_integridad_referencial()
    {
        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $venta = Venta::create([
            'fecha' => Carbon::today(),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        $detalle = DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'tipo' => 'servicio',
            'precio_unitario' => 50000
        ]);

        $resultado = $this->ventaService->eliminarVenta($venta);

        $this->assertTrue($resultado);
        
        // Verificar soft delete en ambas entidades
        $this->assertSoftDeleted('ventas', ['id' => $venta->id]);
        $this->assertDatabaseMissing('detalle_ventas', [
            'venta_id' => $venta->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function service_estadisticas_aplica_filtros_correctamente()
    {
        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        // Crear ventas de prueba
        Venta::create([
            'fecha' => Carbon::create(2025, 6, 1),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 50000
        ]);

        Venta::create([
            'fecha' => Carbon::create(2025, 6, 15),
            'medio_pago' => 'Tarjeta',
            'id_cliente' => $cliente->id,
            'costo_total' => 75000
        ]);

        Venta::create([
            'fecha' => Carbon::create(2025, 7, 1),
            'medio_pago' => 'Efectivo',
            'id_cliente' => $cliente->id,
            'costo_total' => 60000
        ]);

        // Test filtros de fecha
        $estadisticas = $this->ventaService->obtenerEstadisticas([
            'fecha_desde' => '2025-06-01',
            'fecha_hasta' => '2025-06-30'
        ]);

        $this->assertEquals(2, $estadisticas['total_ventas']);
        $this->assertEquals(125000, $estadisticas['monto_total']);
        $this->assertEquals(62500, $estadisticas['promedio_venta']);
        $this->assertCount(2, $estadisticas['ventas_por_medio_pago']);
    }

    /** @test */
    public function dependency_injection_funciona_correctamente()
    {
        // Verificar que el service se puede resolver desde el container
        $ventaService = app(VentaService::class);
        $this->assertInstanceOf(VentaService::class, $ventaService);
    }

    /** @test */
    public function service_maneja_excepciones_de_dominio()
    {
        // Simular un error genérico en el service
        $data = [
            'cliente_id' => 999, // Cliente inexistente
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        $this->expectException(VentaServiceException::class);
        $this->expectExceptionMessage('Cliente con ID 999 no encontrado');

        $this->ventaService->crearVenta($data);
    }

    /** @test */
    public function service_layer_valida_consistencia_de_productos()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 50000
                ]
            ], 200),
            '*/api/servicios/2' => Http::response([
                'error' => 'Not found'
            ], 404)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio'],
                ['producto_id' => 2, 'tipo' => 'servicio']
            ]
        ];

        $this->expectException(VentaServiceException::class);
        $this->expectExceptionMessage('Error al obtener información de productos');

        $this->ventaService->crearVenta($data);
    }

    /** @test */
    public function service_separa_logica_de_negocio_de_persistencia()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel Test',
                    'costo' => 50000
                ]
            ], 200)
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Test',
            'dni' => 12345678,
            'email' => 'juan@test.com'
        ]);

        $data = [
            'cliente_id' => $cliente->id,
            'medio_pago' => 'Efectivo',
            'fecha' => '2025-06-15',
            'items' => [
                ['producto_id' => 1, 'tipo' => 'servicio']
            ]
        ];

        // Test que el service encapsula la lógica pero no sabe sobre implementación de BD
        $venta = $this->ventaService->crearVenta($data);

        // El service devuelve entidades de dominio, no DTOs de BD
        $this->assertInstanceOf(Venta::class, $venta);
        $this->assertTrue($venta->exists);
        $this->assertDatabaseHas('ventas', ['id' => $venta->id]);
    }
}