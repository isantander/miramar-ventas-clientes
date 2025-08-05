<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Cliente;

class ClienteApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function puede_crear_cliente_con_todos_los_campos_requeridos()
    {
        $clienteData = [
            'nombre' => 'Juan Carlos',
            'apellido' => 'Pérez González',
            'dni' => 32456789,
            'email' => 'juan.perez@gmail.com'
        ];

        $response = $this->postJson('/api/clientes', $clienteData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'nombre',
                        'apellido',
                        'dni',
                        'email'
                    ]
                ]);

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'JUAN CARLOS',  // El sistema normaliza a mayúsculas
            'apellido' => 'PéREZ GONZáLEZ',  // strtoupper preserva acentos en PHP
            'dni' => 32456789,
            'email' => 'juan.perez@gmail.com'
        ]);
    }

    /** @test */
    public function no_puede_crear_cliente_con_dni_duplicado()
    {
        Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Existente',
            'dni' => 12345678,
            'email' => 'existente@test.com'
        ]);

        $clienteData = [
            'nombre' => 'Nuevo',
            'apellido' => 'Cliente',
            'dni' => 12345678, // DNI duplicado
            'email' => 'nuevo@test.com'
        ];

        $response = $this->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['dni']);
    }

    /** @test */
    public function no_puede_crear_cliente_con_email_duplicado()
    {
        Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Existente',
            'dni' => 12345678,
            'email' => 'duplicado@test.com'
        ]);

        $clienteData = [
            'nombre' => 'Nuevo',
            'apellido' => 'Cliente',
            'dni' => 87654321,
            'email' => 'duplicado@test.com' // Email duplicado
        ];

        $response = $this->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function puede_listar_todos_los_clientes()
    {
        Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Uno',
            'dni' => 11111111,
            'email' => 'uno@test.com'
        ]);

        Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Dos',
            'dni' => 22222222,
            'email' => 'dos@test.com'
        ]);

        $response = $this->getJson('/api/clientes');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'nombre',
                            'apellido',
                            'dni',
                            'email'
                        ]
                    ]
                ])
                ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function puede_obtener_cliente_por_id()
    {
        $cliente = Cliente::create([
            'nombre' => 'María Elena',
            'apellido' => 'Rodríguez',
            'dni' => 33333333,
            'email' => 'maria@test.com'
        ]);

        $response = $this->getJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $cliente->id,
                        'nombre' => 'María Elena',
                        'apellido' => 'Rodríguez',
                        'dni' => 33333333
                    ]
                ]);
    }

    /** @test */
    public function retorna_404_al_buscar_cliente_inexistente()
    {
        $response = $this->getJson('/api/clientes/999');

        $response->assertStatus(404)
                ->assertJson(['error' => 'Cliente no encontrado']);
    }

    /** @test */
    public function puede_actualizar_cliente_existente()
    {
        $cliente = Cliente::create([
            'nombre' => 'Carlos',
            'apellido' => 'Mendoza',
            'dni' => 44444444,
            'email' => 'carlos@test.com'
        ]);

        $datosActualizados = [
            'nombre' => 'Carlos Eduardo',
            'apellido' => 'Mendoza López',
            'dni' => 44444444, // Mismo DNI
            'email' => 'carlos.eduardo@test.com'
        ];

        $response = $this->putJson("/api/clientes/{$cliente->id}", $datosActualizados);

        $response->assertStatus(200);

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nombre' => 'CARLOS EDUARDO',  // Normalizado a mayúsculas
            'apellido' => 'MENDOZA LóPEZ',  // strtoupper preserva acentos
            'email' => 'carlos.eduardo@test.com'
        ]);
    }

    /** @test */
    public function puede_eliminar_cliente_con_soft_delete()
    {
        $cliente = Cliente::create([
            'nombre' => 'Ana',
            'apellido' => 'Fernández',
            'dni' => 55555555,
            'email' => 'ana@test.com'
        ]);

        $response = $this->deleteJson("/api/clientes/{$cliente->id}");

        $response->assertStatus(204);

        // Verificar soft delete
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    /** @test */
    public function valida_campos_requeridos()
    {
        $response = $this->postJson('/api/clientes', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'nombre',
                    'apellido',
                    'dni',
                    'email'
                ]);
    }

    /** @test */
    public function valida_formato_de_email()
    {
        $clienteData = [
            'nombre' => 'Test',
            'apellido' => 'Email',
            'dni' => 66666666,
            'email' => 'email-invalido'
        ];

        $response = $this->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function valida_dni_numerico()
    {
        $clienteData = [
            'nombre' => 'Test',
            'apellido' => 'DNI',
            'dni' => 'dni-invalido',
            'email' => 'test@dni.com'
        ];

        $response = $this->postJson('/api/clientes', $clienteData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['dni']);
    }

    /** @test */
    public function scope_por_dni_funciona_correctamente()
    {
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Scope',
            'dni' => 77777777,
            'email' => 'scope@test.com'
        ]);

        $clienteEncontrado = Cliente::porDni(77777777)->first();

        $this->assertNotNull($clienteEncontrado);
        $this->assertEquals($cliente->id, $clienteEncontrado->id);
    }

    /** @test */
    public function accessor_nombre_completo_funciona()
    {
        $cliente = Cliente::create([
            'nombre' => 'Pedro',
            'apellido' => 'García Silva',
            'dni' => 88888888,
            'email' => 'pedro@test.com'
        ]);

        $this->assertEquals('Pedro García Silva', $cliente->nombre_completo);
    }
}