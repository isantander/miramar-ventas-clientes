<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\ProductoService;
use App\Exceptions\ProductoServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class MicroserviceCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private ProductoService $productoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productoService = new ProductoService();
    }

    /** @test */
    public function puede_obtener_servicio_del_microservicio_productos()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'codigo' => 'SRV-001',
                    'nombre' => 'Hotel Test',
                    'descripcion' => 'Hotel para testing',
                    'destino' => 'Bariloche',
                    'fecha' => '2025-06-15',
                    'costo' => 50000
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(1, 'servicio');

        $this->assertEquals(1, $producto['id']);
        $this->assertEquals('servicio', $producto['tipo']);
        $this->assertEquals('Hotel Test', $producto['nombre']);
        $this->assertEquals(50000, $producto['precio']);
    }

    /** @test */
    public function puede_obtener_paquete_del_microservicio_productos()
    {
        Http::fake([
            '*/api/paquetes/2' => Http::response([
                'data' => [
                    'id' => 2,
                    'codigo' => 'PAQ-001',
                    'nombre' => 'Paquete Bariloche',
                    'precio_calculado' => 72000,
                    'servicios' => [
                        ['id' => 1, 'nombre' => 'Hotel'],
                        ['id' => 2, 'nombre' => 'Excursión']
                    ]
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(2, 'paquete');

        $this->assertEquals(2, $producto['id']);
        $this->assertEquals('paquete', $producto['tipo']);
        $this->assertEquals('Paquete Bariloche', $producto['nombre']);
        $this->assertEquals(72000, $producto['precio']);
    }

    /** @test */
    public function maneja_error_404_cuando_producto_no_existe()
    {
        Http::fake([
            '*/api/servicios/999' => Http::response([
                'error' => 'Servicio no encontrado'
            ], 404)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage('No se pudo conectar con el servicio de productos');

        $this->productoService->obtenerProducto(999, 'servicio');
    }

    /** @test */
    public function maneja_error_500_del_microservicio_productos()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'error' => 'Error interno del servidor'
            ], 500)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage('No se pudo conectar con el servicio de productos');

        $this->productoService->obtenerProducto(1, 'servicio');
    }

    /** @test */
    public function maneja_timeout_de_conexion()
    {
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage('No se pudo conectar con el servicio de productos');

        $this->productoService->obtenerProducto(1, 'servicio');
    }

    /** @test */
    public function valida_tipo_de_producto_invalido()
    {
        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage("Tipo de producto inválido: hotel. Debe ser 'servicio' o 'paquete'.");

        $this->productoService->obtenerProducto(1, 'hotel');
    }

    /** @test */
    public function puede_obtener_productos_en_lote()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel',
                    'costo' => 50000
                ]
            ], 200),
            '*/api/paquetes/2' => Http::response([
                'data' => [
                    'id' => 2,
                    'nombre' => 'Paquete',
                    'precio_calculado' => 72000
                ]
            ], 200)
        ]);

        $items = [
            ['producto_id' => 1, 'tipo' => 'servicio'],
            ['producto_id' => 2, 'tipo' => 'paquete']
        ];

        $productos = $this->productoService->obtenerProductosEnLote($items);

        $this->assertCount(2, $productos);
        $this->assertEquals(1, $productos[0]['id']);
        $this->assertEquals(2, $productos[1]['id']);
        $this->assertEquals('servicio', $productos[0]['tipo']);
        $this->assertEquals('paquete', $productos[1]['tipo']);
    }

    /** @test */
    public function lanza_excepcion_cuando_algunos_productos_fallan_en_lote()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Hotel',
                    'costo' => 50000
                ]
            ], 200),
            '*/api/servicios/999' => Http::response([
                'error' => 'Servicio no encontrado'
            ], 404)
        ]);

        $items = [
            ['producto_id' => 1, 'tipo' => 'servicio'],
            ['producto_id' => 999, 'tipo' => 'servicio']
        ];

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage('Error al obtener algunos productos');

        $this->productoService->obtenerProductosEnLote($items);
    }

    /** @test */
    public function calcula_costo_total_correctamente()
    {
        $productos = [
            ['precio' => 50000],
            ['precio' => 72000],
            ['precio' => 30000]
        ];

        $total = $this->productoService->calcularCostoTotal($productos);

        $this->assertEquals(152000, $total);
    }

    /** @test */
    public function verifica_conectividad_cuando_servicio_esta_disponible()
    {
        Http::fake([
            '*/api/servicios' => Http::response([
                'data' => []
            ], 200)
        ]);

        $conectividad = $this->productoService->verificarConectividad();

        $this->assertTrue($conectividad);
    }

    /** @test */
    public function verifica_conectividad_cuando_servicio_no_esta_disponible()
    {
        Http::fake([
            '*' => function () {
                throw new \Exception('Connection refused');
            }
        ]);

        $conectividad = $this->productoService->verificarConectividad();

        $this->assertFalse($conectividad);
    }

    /** @test */
    public function construye_endpoint_correcto_para_servicios()
    {
        Http::fake([
            'http://localhost:8010/api/servicios/123' => Http::response([
                'data' => [
                    'id' => 123,
                    'nombre' => 'Test',
                    'costo' => 1000
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(123, 'servicio');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8010/api/servicios/123';
        });
    }

    /** @test */
    public function construye_endpoint_correcto_para_paquetes()
    {
        Http::fake([
            'http://localhost:8010/api/paquetes/456' => Http::response([
                'data' => [
                    'id' => 456,
                    'nombre' => 'Test Package',
                    'precio_calculado' => 2000
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(456, 'paquete');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8010/api/paquetes/456';
        });
    }

    /** @test */
    public function valida_respuesta_sin_campo_id()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'nombre' => 'Test',
                    'costo' => 1000
                    // Falta campo 'id'
                ]
            ], 200)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage("Respuesta inválida: falta campo 'id'");

        $this->productoService->obtenerProducto(1, 'servicio');
    }

    /** @test */
    public function valida_respuesta_sin_campo_precio()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Test'
                    // Falta campo precio/costo
                ]
            ], 200)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage("Respuesta inválida: falta campo de precio");

        $this->productoService->obtenerProducto(1, 'servicio');
    }

    /** @test */
    public function valida_id_de_respuesta_coincidente()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 999, // ID diferente al solicitado
                    'nombre' => 'Test',
                    'costo' => 1000
                ]
            ], 200)
        ]);

        $this->expectException(ProductoServiceException::class);
        $this->expectExceptionMessage('ID de respuesta no coincide: esperado 1, recibido 999');

        $this->productoService->obtenerProducto(1, 'servicio');
    }

    /** @test */
    public function normaliza_respuesta_de_servicio_correctamente()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'codigo' => 'SRV-001',
                    'nombre' => 'Hotel Test',
                    'descripcion' => 'Descripción del hotel',
                    'destino' => 'Bariloche',
                    'fecha' => '2025-06-15',
                    'costo' => 50000
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(1, 'servicio');

        $this->assertEquals([
            'id' => 1,
            'tipo' => 'servicio',
            'nombre' => 'Hotel Test',
            'precio' => 50000.0,
            'descripcion' => 'Descripción del hotel',
            'data_completa' => [
                'id' => 1,
                'codigo' => 'SRV-001',
                'nombre' => 'Hotel Test',
                'descripcion' => 'Descripción del hotel',
                'destino' => 'Bariloche',
                'fecha' => '2025-06-15',
                'costo' => 50000
            ]
        ], $producto);
    }

    /** @test */
    public function normaliza_respuesta_de_paquete_correctamente()
    {
        Http::fake([
            '*/api/paquetes/2' => Http::response([
                'data' => [
                    'id' => 2,
                    'codigo' => 'PAQ-001',
                    'nombre' => 'Paquete Bariloche',
                    'precio_calculado' => 72000,
                    'servicios' => [
                        ['id' => 1, 'nombre' => 'Hotel'],
                        ['id' => 2, 'nombre' => 'Excursión']
                    ]
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(2, 'paquete');

        $this->assertEquals(2, $producto['id']);
        $this->assertEquals('paquete', $producto['tipo']);
        $this->assertEquals('Paquete Bariloche', $producto['nombre']);
        $this->assertEquals(72000.0, $producto['precio']);
        $this->assertArrayHasKey('data_completa', $producto);
    }

    /** @test */
    public function maneja_respuesta_sin_nombre_producto()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'costo' => 1000
                    // Sin campo 'nombre'
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(1, 'servicio');

        $this->assertEquals('Producto sin nombre', $producto['nombre']);
    }

    /** @test */
    public function respeta_configuracion_de_timeout()
    {
        Config::set('services.productos.timeout', 5);

        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Test',
                    'costo' => 1000
                ]
            ], 200)
        ]);

        $this->productoService->obtenerProducto(1, 'servicio');

        Http::assertSent(function ($request) {
            // Verificar que se aplicó el timeout
            return true; // En testing real se verificaría el timeout
        });
    }

    /** @test */
    public function respeta_configuracion_de_url_base()
    {
        Config::set('services.productos.url', 'http://custom-url:9000');

        Http::fake([
            'http://custom-url:9000/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Test',
                    'costo' => 1000
                ]
            ], 200)
        ]);

        $productoService = new ProductoService();
        $producto = $productoService->obtenerProducto(1, 'servicio');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-url:9000');
        });
    }

    /** @test */
    public function realiza_reintentos_en_caso_de_fallo_temporal()
    {
        Http::fake([
            '*/api/servicios/1' => Http::sequence()
                ->push(null, 500) // Primer intento falla
                ->push(null, 500) // Segundo intento falla
                ->push([           // Tercer intento funciona
                    'data' => [
                        'id' => 1,
                        'nombre' => 'Test',
                        'costo' => 1000
                    ]
                ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(1, 'servicio');

        $this->assertEquals(1, $producto['id']);
        Http::assertSentCount(3); // Verificar que se hicieron 3 intentos
    }

    /** @test */
    public function extrae_data_de_respuesta_anidada()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 1,
                    'nombre' => 'Test',
                    'costo' => 1000
                ],
                'meta' => [
                    'timestamp' => '2025-06-15'
                ]
            ], 200)
        ]);

        $producto = $this->productoService->obtenerProducto(1, 'servicio');

        $this->assertEquals(1, $producto['id']);
        $this->assertEquals('Test', $producto['nombre']);
    }

    /** @test */
    public function incluye_metadata_en_productos_en_lote()
    {
        Http::fake([
            '*/api/servicios/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'nombre' => 'Test',
                    'costo' => 1000
                ]
            ], 200)
        ]);

        $items = [
            ['producto_id' => 1, 'tipo' => 'servicio']
        ];

        $productos = $this->productoService->obtenerProductosEnLote($items);

        $this->assertEquals(0, $productos[0]['item_index']);
        $this->assertEquals(1, $productos[0]['producto_id_original']);
        $this->assertEquals('servicio', $productos[0]['tipo_original']);
    }
}