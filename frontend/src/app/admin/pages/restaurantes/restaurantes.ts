
import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

interface Restaurante {
  id: number;
  nombre: string;
  slug: string;
  administrador: string;
  correo: string;
  estado: 'Activo' | 'Suspendido';
  fecha: string;
}

@Component({
  selector: 'app-admin-restaurantes',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './restaurantes.html',
  styleUrl: './restaurantes.css'
})
export class Restaurantes {
  busqueda = '';
  filtroEstado = 'Todos';

  // Datos temporales para diseñar y probar la interfaz.
  restaurantes: Restaurante[] = [
    {
      id: 1,
      nombre: 'Restaurante de Prueba',
      slug: 'restaurante-prueba',
      administrador: 'Administrador de prueba',
      correo: 'admin@ejemplo.com',
      estado: 'Activo',
      fecha: '2026-10-20'
    },
    {
      id: 2,
      nombre: 'Sabores del Caribe',
      slug: 'sabores-del-caribe',
      administrador: 'Carlos Martínez',
      correo: 'carlos@ejemplo.com',
      estado: 'Activo',
      fecha: '2026-10-22'
    },
    {
      id: 3,
      nombre: 'La Cocina de Casa',
      slug: 'la-cocina-de-casa',
      administrador: 'María Rodríguez',
      correo: 'maria@ejemplo.com',
      estado: 'Suspendido',
      fecha: '2026-10-25'
    }
  ];

  get restaurantesFiltrados(): Restaurante[] {
    const termino = this.busqueda.trim().toLowerCase();

    return this.restaurantes.filter((restaurante) => {
      const coincideBusqueda = [
        restaurante.nombre,
        restaurante.slug,
        restaurante.administrador,
        restaurante.correo
      ].some(valor => valor.toLowerCase().includes(termino));

      const coincideEstado =
        this.filtroEstado === 'Todos' ||
        restaurante.estado === this.filtroEstado;

      return coincideBusqueda && coincideEstado;
    });
  }

  get totalActivos(): number {
    return this.restaurantes.filter(r => r.estado === 'Activo').length;
  }

  get totalSuspendidos(): number {
    return this.restaurantes.filter(r => r.estado === 'Suspendido').length;
  }
}
