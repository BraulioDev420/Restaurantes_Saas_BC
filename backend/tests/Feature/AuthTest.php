<?php
namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(array $datos = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre'     => 'Usuario de prueba',
            'correo'     => uniqid('auth_') . '@example.com',
            'contrasena' => Hash::make('Password123!'),
            'estado'     => 'activo',
        ], $datos));
    }

    public function test_usuario_puede_iniciar_sesion_con_credenciales_validas(): void
    {
        $usuario = $this->crearUsuario([
            'correo' => 'login@example.com',
        ]);

        $respuesta = $this->postJson('/api/login', [
            'correo'     => 'login@example.com',
            'contrasena' => 'Password123!',
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('tipo_token', 'Bearer')
            ->assertJsonPath('usuario.id_usuario', $usuario->id_usuario)
            ->assertJsonPath('usuario.correo', 'login@example.com')
            ->assertJsonStructure([
                'mensaje',
                'token',
                'tipo_token',
                'usuario' => [
                    'id_usuario',
                    'nombre',
                    'correo',
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rechaza_una_contrasena_incorrecta(): void
    {
        $this->crearUsuario([
            'correo' => 'login-error@example.com',
        ]);

        $this->postJson('/api/login', [
            'correo'     => 'login-error@example.com',
            'contrasena' => 'incorrecta',
        ])
            ->assertUnauthorized()
            ->assertJsonPath(
                'mensaje',
                'Credenciales incorrectas o usuario inactivo.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rechaza_un_usuario_inactivo(): void
    {
        $this->crearUsuario([
            'correo' => 'inactivo@example.com',
            'estado' => 'inactivo',
        ]);

        $this->postJson('/api/login', [
            'correo'     => 'inactivo@example.com',
            'contrasena' => 'Password123!',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_valida_los_campos_obligatorios(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'correo',
                'contrasena',
            ]);
    }

    public function test_usuario_autenticado_puede_consultar_su_perfil(): void
    {
        $usuario = $this->crearUsuario();

        $token = $usuario->createToken('prueba-auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/usuario')
            ->assertOk()
            ->assertJsonPath('usuario.id_usuario', $usuario->id_usuario)
            ->assertJsonPath('usuario.correo', $usuario->correo)
            ->assertJsonMissingPath('usuario.contrasena');
    }

    public function test_no_se_puede_consultar_el_perfil_sin_autenticacion(): void
    {
        $this->getJson('/api/usuario')->assertUnauthorized();
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        $usuario = $this->crearUsuario();

        $token = $usuario->createToken('prueba-logout')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath(
                'mensaje',
                'Sesión cerrada correctamente.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);

// Limpiar los guards para simular una petición nueva.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/usuario')
            ->assertUnauthorized();
    }
}
