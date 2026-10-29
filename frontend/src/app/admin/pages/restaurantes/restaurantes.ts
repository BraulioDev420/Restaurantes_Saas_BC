
import { CommonModule } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

interface Restaurante {
  id: number;
  nombre: string;
  slug: string;
  administrador: string | null;
  correo: string | null;
  estado: string;
  fecha: string | null;
}

interface RespuestaRestaurantes {
  restaurantes: Restaurante[];
}

interface RespuestaEstado {
  mensaje: string;
  restaurante: {
    id: number;
    nombre: string;
    estado: string;
  };
}

@Component({
  selector: 'app-admin-restaurantes',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './restaurantes.html',
  styleUrl: './restaurantes.css'
})
export class Restaurantes implements OnInit {
  busqueda = '';
  filtroEstado = 'Todos';

  restaurantes = signal<Restaurante[]>([]);
  cargando = signal(false);
  error = signal('');
  mensaje = signal('');
  restauranteProcesando = signal<number | null>(null);

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.cargarRestaurantes();
  }

  cargarRestaurantes(): void {
    this.cargando.set(true);
    this.error.set('');

    this.http.get<RespuestaRestaurantes>('/api/admin/restaurantes').subscribe({
      next: (respuesta) => {
        this.restaurantes.set(respuesta.restaurantes);
        this.cargando.set(false);
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al cargar restaurantes:', err);
        this.error.set(
          err.status === 401
            ? 'Tu sesión ha expirado. Inicia sesión nuevamente.'
            : err.status === 403
              ? 'No tienes permisos para consultar los restaurantes.'
              : 'No se pudieron cargar los restaurantes. Inténtalo nuevamente.'
        );
        this.cargando.set(false);
      }
    });
  }

  cambiarEstado(restaurante: Restaurante): void {
    const estaActivo = restaurante.estado.toLowerCase() === 'activo';

    const nuevoEstado = estaActivo ? 'suspendido' : 'activo';

    const accion = estaActivo ? 'suspender' : 'reactivar';

    const confirmado = window.confirm(
      `¿Seguro que deseas ${accion} el restaurante "${restaurante.nombre}"?`
    );

    if (!confirmado) {
      return;
    }

    this.error.set('');
    this.mensaje.set('');
    this.restauranteProcesando.set(restaurante.id);

    this.http.patch<RespuestaEstado>(
      `/api/admin/restaurantes/${restaurante.id}/estado`,
      { estado: nuevoEstado }
    ).subscribe({
      next: (respuesta) => {
        this.restaurantes.update(lista =>
          lista.map(item =>
            item.id === restaurante.id
              ? { ...item, estado: respuesta.restaurante.estado }
              : item
          )
        );

        this.mensaje.set(respuesta.mensaje);
        this.restauranteProcesando.set(null);
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al cambiar el estado:', err);

        this.error.set(
          err.status === 401
            ? 'Tu sesión ha expirado. Inicia sesión nuevamente.'
            : err.status === 403
              ? 'No tienes permisos para cambiar el estado de este restaurante.'
              : err.status === 404
                ? 'El restaurante ya no existe. Actualiza el listado.'
                : 'No se pudo cambiar el estado del restaurante. Inténtalo nuevamente.'
        );

        this.restauranteProcesando.set(null);
      }
    });
  }

  get restaurantesFiltrados(): Restaurante[] {
    const termino = this.busqueda.trim().toLowerCase();

    return this.restaurantes().filter((restaurante) => {
      const coincideBusqueda = [
        restaurante.nombre,
        restaurante.slug,
        restaurante.administrador ?? '',
        restaurante.correo ?? ''
      ].some(valor => valor.toLowerCase().includes(termino));

      const estado = this.normalizarEstado(restaurante.estado);

      const coincideEstado =
        this.filtroEstado === 'Todos' ||
        estado === this.filtroEstado;

      return coincideBusqueda && coincideEstado;
    });
  }

  get totalActivos(): number {
    return this.restaurantes().filter(
      r => this.normalizarEstado(r.estado) === 'Activo'
    ).length;
  }

  get totalSuspendidos(): number {
    return this.restaurantes().filter(
      r => this.normalizarEstado(r.estado) === 'Suspendido'
    ).length;
  }

  private normalizarEstado(estado: string): string {
    switch (estado.toLowerCase()) {
      case 'activo':
        return 'Activo';
      case 'suspendido':
        return 'Suspendido';
      default:
        return estado;
    }
  }
}
