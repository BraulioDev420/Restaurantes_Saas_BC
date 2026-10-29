
import { CommonModule } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, signal } from '@angular/core';
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

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class Dashboard implements OnInit {
  restaurantes = signal<Restaurante[]>([]);
  cargando = signal(true);
  error = signal('');

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.cargarDashboard();
  }

  cargarDashboard(): void {
    this.cargando.set(true);
    this.error.set('');

    this.http.get<RespuestaRestaurantes>(
      '/api/admin/restaurantes'
    ).subscribe({
      next: (respuesta) => {
        this.restaurantes.set(respuesta.restaurantes ?? []);
        this.cargando.set(false);
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al cargar el dashboard:', err);

        this.error.set(
          err.status === 401
            ? 'Tu sesión ha expirado. Inicia sesión nuevamente.'
            : err.status === 403
              ? 'No tienes permisos para consultar el dashboard.'
              : 'No se pudieron cargar las estadísticas. Inténtalo nuevamente.'
        );

        this.cargando.set(false);
      }
    });
  }

  get totalRestaurantes(): number {
    return this.restaurantes().length;
  }

  get totalActivos(): number {
    return this.restaurantes().filter(
      restaurante => restaurante.estado.toLowerCase() === 'activo'
    ).length;
  }

  get totalSuspendidos(): number {
    return this.restaurantes().filter(
      restaurante => restaurante.estado.toLowerCase() === 'suspendido'
    ).length;
  }

  get totalAdministradores(): number {
    const correos = this.restaurantes()
      .filter(restaurante => restaurante.administrador)
      .map(restaurante => restaurante.correo?.trim().toLowerCase())
      .filter((correo): correo is string => !!correo);

    return new Set(correos).size;
  }

  get restaurantesRecientes(): Restaurante[] {
    return [...this.restaurantes()]
      .sort((a, b) => {
        const fechaA = a.fecha ? new Date(a.fecha).getTime() : 0;
        const fechaB = b.fecha ? new Date(b.fecha).getTime() : 0;
        return fechaB - fechaA;
      })
      .slice(0, 5);
  }
}
