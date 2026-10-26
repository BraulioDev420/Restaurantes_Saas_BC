import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';

interface Producto {
  id_producto: number;
  nombre: string;
  descripcion: string | null;
  precio: string | number;
  imagen_url: string | null;
}

interface Categoria {
  id_categoria: number;
  nombre: string;
  descripcion: string | null;
  productos: Producto[];
}

interface MenuPublico {
  restaurante: {
    nombre: string;
    slug: string;
    descripcion: string | null;
    logo_url: string | null;
    telefono: string | null;
    direccion: string | null;
  };
  categorias: Categoria[];
}

interface ItemCarrito {
  producto: Producto;
  cantidad: number;
  observaciones: string;
}

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App implements OnInit {
  private http = inject(HttpClient);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);
  readonly Number = Number;

  slug = 'restaurante-prueba';
  menu: MenuPublico | null = null;
  carrito: ItemCarrito[] = [];

  busqueda = '';
  categoriaSeleccionada: number | null = null;
  mostrarCarrito = false;
  cargando = true;
  enviando = false;
  error = '';
  mensaje = '';
  pedidoCreado: any = null;

  cliente = {
    nombre_cliente: '',
    telefono_cliente: '',
    correo_cliente: '',
    tipo_entrega: 'domicilio',
    direccion_entrega: '',
    notas: '',
  };

  ngOnInit(): void {
    this.route.paramMap.subscribe((params) => {
      const slug = params.get('slug') || 'restaurante-prueba';

      console.log('[RUTA] Slug recibido:', slug);

      this.slug = slug;
      this.cargarMenu();
    });
  }

  cargarMenu(): void {
    console.log('[MENÚ] Iniciando carga:', this.slug);

    this.cargando = true;
    this.error = '';
    this.menu = null;

    this.http.get<MenuPublico>(`/api/menu/${encodeURIComponent(this.slug)}`).subscribe({
      next: (data) => {
        console.log('[MENÚ] Respuesta recibida:', data);

        this.menu = data;
        this.cargando = false;
        this.cdr.detectChanges();

        console.log('[MENÚ] Estado final:', {
          cargando: this.cargando,
          menuRecibido: !!this.menu,
          categorias: this.menu?.categorias?.length,
        });

        setTimeout(() => {
          console.log('[MENÚ] Estado después de 1 segundo:', {
            cargando: this.cargando,
            menuRecibido: !!this.menu,
            categorias: this.menu?.categorias?.length,
          });
        }, 1000);
      },
      error: (err) => {
        console.error('[MENÚ] Error HTTP:', err);

        this.cargando = false;
        this.error =
          err.status === 404
            ? 'No encontramos este restaurante o su menú no está disponible.'
            : 'No pudimos cargar el menú. Verifica que Laravel esté funcionando.';
      },
    });
  }

  get categoriasVisibles(): Categoria[] {
    if (!this.menu) return [];

    return this.menu.categorias
      .map((categoria) => ({
        ...categoria,
        productos: categoria.productos.filter((producto) => {
          const texto = `${producto.nombre} ${producto.descripcion ?? ''}`.toLowerCase();

          const coincideBusqueda = texto.includes(this.busqueda.trim().toLowerCase());

          return coincideBusqueda;
        }),
      }))
      .filter((categoria) => {
        const coincideCategoria =
          this.categoriaSeleccionada === null ||
          categoria.id_categoria === this.categoriaSeleccionada;

        return coincideCategoria && categoria.productos.length > 0;
      });
  }

  seleccionarCategoria(id: number | null): void {
    this.categoriaSeleccionada = id;
  }

  agregar(producto: Producto): void {
    const existente = this.carrito.find(
      (item) => item.producto.id_producto === producto.id_producto,
    );

    if (existente) {
      if (existente.cantidad < 100) existente.cantidad++;
    } else {
      this.carrito.push({
        producto,
        cantidad: 1,
        observaciones: '',
      });
    }

    this.mensaje = '';
    this.pedidoCreado = null;
  }

  cambiarCantidad(item: ItemCarrito, cambio: number): void {
    item.cantidad += cambio;

    if (item.cantidad <= 0) {
      this.quitar(item);
    } else if (item.cantidad > 100) {
      item.cantidad = 100;
    }
  }

  quitar(item: ItemCarrito): void {
    this.carrito = this.carrito.filter((actual) => actual !== item);
  }

  get cantidadArticulos(): number {
    return this.carrito.reduce((total, item) => total + item.cantidad, 0);
  }

  get subtotal(): number {
    return this.carrito.reduce(
      (total, item) => total + Number(item.producto.precio) * item.cantidad,
      0,
    );
  }

  finalizarPedido(): void {
    this.error = '';
    this.mensaje = '';
    this.pedidoCreado = null;

    if (!this.menu || this.carrito.length === 0) {
      this.error = 'Agrega al menos un producto al carrito.';
      return;
    }

    if (this.cliente.tipo_entrega === 'domicilio' && !this.cliente.direccion_entrega.trim()) {
      this.error = 'Escribe la dirección para recibir el pedido.';
      return;
    }

    const body = {
      nombre_cliente: this.cliente.nombre_cliente.trim(),
      telefono_cliente: this.cliente.telefono_cliente.trim(),
      correo_cliente: this.cliente.correo_cliente.trim() || null,
      tipo_entrega: this.cliente.tipo_entrega,
      direccion_entrega:
        this.cliente.tipo_entrega === 'domicilio' ? this.cliente.direccion_entrega.trim() : null,
      notas: this.cliente.notas.trim() || null,
      detalles: this.carrito.map((item) => ({
        id_producto: item.producto.id_producto,
        cantidad: item.cantidad,
        observaciones: item.observaciones.trim() || null,
      })),
    };

    this.enviando = true;

    this.http.post<any>(`/api/pedido-publico/${encodeURIComponent(this.slug)}`, body).subscribe({
      next: (respuesta) => {
        console.log('[PEDIDO] Respuesta recibida:', respuesta);

        this.pedidoCreado = respuesta.pedido;
        this.mensaje = respuesta.mensaje || 'Pedido enviado correctamente.';
        this.carrito = [];
        this.mostrarCarrito = false;
        this.enviando = false;

        this.cliente = {
          nombre_cliente: '',
          telefono_cliente: '',
          correo_cliente: '',
          tipo_entrega: 'domicilio',
          direccion_entrega: '',
          notas: '',
        };

        console.log('[PEDIDO] Estado actualizado:', {
          enviando: this.enviando,
          pedidoCreado: this.pedidoCreado,
          mensaje: this.mensaje,
        });
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('[PEDIDO] Error HTTP:', err);

        this.enviando = false;

        if (err.status === 422) {
          const errores = err.error?.errors;
          this.error = errores
            ? Object.values(errores).flat().join(' ')
            : 'Revisa los datos del pedido.';
        } else if (err.status === 404) {
          this.error = 'El restaurante ya no está disponible.';
        } else if (err.status === 429) {
          this.error = 'Se enviaron demasiadas solicitudes. Espera un momento.';
        } else {
          this.error = 'No se pudo enviar el pedido. Inténtalo nuevamente.';
        }
        this.cdr.detectChanges();
      },
      complete: () => {
        console.log('[PEDIDO] Petición completada');
        this.cdr.detectChanges();
      },
    });
    this.cdr.detectChanges();
  }
}
