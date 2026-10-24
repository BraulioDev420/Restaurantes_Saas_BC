<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\HistorialEstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\RestauranteUsuario;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RestaurantePruebaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Crear el restaurante.
            $restaurante = Restaurante::firstOrCreate(
                ['slug' => 'restaurante-prueba'],
                [
                    'nombre' => 'Restaurante de Prueba',
                    'descripcion' => 'Datos de prueba para el desarrollo.',
                    'estado' => 'activo',
                ]
            );

            // 2. Crear el usuario administrador.
            $usuario = Usuario::firstOrCreate(
                ['correo' => 'admin.prueba@example.com'],
                [
                    'nombre' => 'Administrador de Prueba',
                    'contrasena' => Hash::make(
                        bin2hex(random_bytes(16))
                    ),
                    'estado' => 'activo',
                ]
            );

            // 3. Asociar el usuario al restaurante.
            RestauranteUsuario::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'id_usuario' => $usuario->id_usuario,
                ],
                ['rol' => 'administrador']
            );

            // 4. Crear una categoría.
            $categoria = Categoria::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'nombre' => 'Hamburguesas',
                ],
                [
                    'descripcion' => 'Hamburguesas de prueba.',
                    'orden_menu' => 1,
                    'activo' => true,
                ]
            );

            // 5. Crear un producto.
            $producto = Producto::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'nombre' => 'Hamburguesa de prueba',
                ],
                [
                    'id_categoria' => $categoria->id_categoria,
                    'descripcion' => 'Producto de demostración.',
                    'precio' => 15000,
                    'disponible' => true,
                ]
            );

            // 6. Crear un cliente.
            $cliente = Cliente::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'telefono' => '3000000000',
                ],
                [
                    'nombre' => 'Cliente de Prueba',
                    'correo' => 'cliente.prueba@example.com',
                ]
            );

            // 7. Crear un pedido.
            $pedido = Pedido::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'numero_pedido' => 'PRUEBA-001',
                ],
                [
                    'id_cliente' => $cliente->id_cliente,
                    'nombre_cliente' => $cliente->nombre,
                    'telefono_cliente' => $cliente->telefono,
                    'correo_cliente' => $cliente->correo,
                    'tipo_entrega' => 'recoger',
                    'subtotal' => 15000,
                    'costo_domicilio' => 0,
                    'total' => 15000,
                    'estado' => 'pendiente',
                ]
            );

            // 8. Agregar el producto al pedido.
            DetallePedido::firstOrCreate(
                [
                    'id_pedido' => $pedido->id_pedido,
                    'nombre_producto' => $producto->nombre,
                ],
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'id_producto' => $producto->id_producto,
                    'cantidad' => 1,
                    'precio_unitario' => 15000,
                    'subtotal' => 15000,
                ]
            );

            // 9. Registrar el estado inicial del pedido.
            HistorialEstadoPedido::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'id_pedido' => $pedido->id_pedido,
                    'estado' => 'pendiente',
                ],
                [
                    'id_cambiado_por' => $usuario->id_usuario,
                    'notas' => 'Pedido creado para pruebas.',
                ]
            );

            // 10. Registrar un pago pendiente.
            Pago::firstOrCreate(
                [
                    'id_restaurante' => $restaurante->id_restaurante,
                    'id_pedido' => $pedido->id_pedido,
                    'metodo_pago' => 'transferencia',
                ],
                [
                    'monto' => 15000,
                    'estado' => 'pendiente',
                    'observaciones' => 'Pago de prueba sin verificar.',
                ]
            );
        });

        $this->command->info(
            'Datos de prueba creados o ya existentes correctamente.'
        );
    }
}