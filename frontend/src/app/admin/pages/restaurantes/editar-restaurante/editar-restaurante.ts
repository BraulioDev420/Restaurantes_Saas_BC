
import { CommonModule } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

interface RestauranteEditable {
  id_restaurante: number;
  nombre: string;
  slug: string;
  descripcion: string | null;
  telefono: string | null;
  correo: string | null;
  direccion: string | null;
}

interface RespuestaRestaurante {
  restaurante: RestauranteEditable;
}

@Component({
  selector: 'app-editar-restaurante',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './editar-restaurante.html',
  styleUrl: './editar-restaurante.css'
})
export class EditarRestaurante implements OnInit {
  id = 0;

  restaurante = {
    nombre: '',
    slug: '',
    descripcion: '',
    telefono: '',
    correo: '',
    direccion: ''
  };

  cargando = signal(true);
  guardando = signal(false);
  error = signal('');
  mensaje = signal('');

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    const idParametro = this.route.snapshot.paramMap.get('id');
    const id = Number(idParametro);

    if (!idParametro || !Number.isInteger(id) || id < 1) {
      this.error.set('El identificador del restaurante no es válido.');
      this.cargando.set(false);
      return;
    }

    this.id = id;
    this.cargarRestaurante();
  }

  cargarRestaurante(): void {
    this.cargando.set(true);
    this.error.set('');

    this.http.get<RespuestaRestaurante>(
      `/api/admin/restaurantes/${this.id}`
    ).subscribe({
      next: (respuesta) => {
        const r = respuesta.restaurante;

        if (!r) {
          this.error.set('El servidor no devolvió los datos del restaurante.');
          this.cargando.set(false);
          return;
        }

        this.restaurante = {
          nombre: r.nombre ?? '',
          slug: r.slug ?? '',
          descripcion: r.descripcion ?? '',
          telefono: r.telefono ?? '',
          correo: r.correo ?? '',
          direccion: r.direccion ?? ''
        };

        this.cargando.set(false);
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al cargar el restaurante:', err);

        this.error.set(
          err.status === 401
            ? 'Tu sesión ha expirado. Inicia sesión nuevamente.'
            : err.status === 403
              ? 'No tienes permisos para editar este restaurante.'
              : err.status === 404
                ? 'El restaurante no existe o fue eliminado.'
                : 'No se pudieron cargar los datos del restaurante.'
        );

        this.cargando.set(false);
      }
    });
  }

  guardar(): void {
    if (this.guardando() || this.cargando()) return;

    this.error.set('');
    this.mensaje.set('');

    const datos = {
      nombre: this.restaurante.nombre.trim(),
      slug: this.restaurante.slug.trim(),
      descripcion: this.restaurante.descripcion.trim() || null,
      telefono: this.restaurante.telefono.trim() || null,
      correo: this.restaurante.correo.trim() || null,
      direccion: this.restaurante.direccion.trim() || null
    };

    this.guardando.set(true);

    this.http.put<{ mensaje?: string }>(
      `/api/admin/restaurantes/${this.id}`,
      datos
    ).subscribe({
      next: (respuesta) => {
        this.mensaje.set(
          respuesta.mensaje || 'Restaurante actualizado correctamente.'
        );
        this.guardando.set(false);

        setTimeout(() => {
          this.router.navigate(['/admin/restaurantes']);
        }, 800);
      },
      error: (err: HttpErrorResponse) => {
        console.error('Error al actualizar el restaurante:', err);

        if (err.status === 422) {
          const errores = err.error?.errors as
            Record<string, string[]> | undefined;

          this.error.set(
            errores
              ? Object.values(errores).flat().join(' ')
              : 'Revisa los datos ingresados e inténtalo nuevamente.'
          );
        } else {
          this.error.set(
            err.status === 401
              ? 'Tu sesión ha expirado. Inicia sesión nuevamente.'
              : err.status === 403
                ? 'No tienes permisos para editar este restaurante.'
                : err.status === 404
                  ? 'El restaurante ya no existe.'
                  : 'No se pudo actualizar el restaurante. Inténtalo nuevamente.'
          );
        }

        this.guardando.set(false);
      }
    });
  }
}
