
import { CommonModule } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

interface RestauranteCreado {
  id: number;
  nombre: string;
  slug: string;
  administrador: string | null;
  correo: string | null;
  estado: string;
  fecha: string | null;
}

interface RespuestaCrearRestaurante {
  mensaje: string;
  restaurante: RestauranteCreado;
}

@Component({
  selector: 'app-nuevo-restaurante',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './nuevo-restaurante.html',
  styleUrl: './nuevo-restaurante.css'
})
export class NuevoRestaurante {
  guardando = signal(false);
  mensaje = signal('');
  error = signal('');

  restaurante = {
    nombre: '',
    slug: '',
    descripcion: '',
    telefono: '',
    correo: '',
    direccion: '',
    nombreAdministrador: '',
    correoAdministrador: '',
    contrasena: '',
    confirmarContrasena: ''
  };

  constructor(
    private http: HttpClient,
    private router: Router
  ) {}

  generarSlug(): void {
    this.restaurante.slug = this.restaurante.nombre
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  guardar(): void {
    this.mensaje.set('');
    this.error.set('');

    if (this.guardando()) {
      return;
    }

    if (this.restaurante.contrasena.length < 8) {
      this.error.set('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (
      this.restaurante.contrasena !==
      this.restaurante.confirmarContrasena
    ) {
      this.error.set('Las contraseñas no coinciden.');
      return;
    }

    const datos = {
      nombre: this.restaurante.nombre.trim(),
      slug: this.restaurante.slug.trim(),
      descripcion: this.restaurante.descripcion.trim() || null,
      telefono: this.restaurante.telefono.trim() || null,
      correo: this.restaurante.correo.trim() || null,
      direccion: this.restaurante.direccion.trim() || null,
      nombre_administrador:
        this.restaurante.nombreAdministrador.trim(),
      correo_administrador:
        this.restaurante.correoAdministrador.trim(),
      contrasena: this.restaurante.contrasena
    };

    this.guardando.set(true);

    this.http
      .post<RespuestaCrearRestaurante>(
        '/api/admin/restaurantes',
        datos
      )
      .subscribe({
        next: (respuesta) => {
          this.guardando.set(false);
          this.mensaje.set(
            respuesta.mensaje || 'Restaurante creado correctamente.'
          );

          // Regresar al directorio para consultar los datos reales.
          this.router.navigate(['/admin/restaurantes']);
        },
        error: (err: HttpErrorResponse) => {
          this.guardando.set(false);

          if (err.status === 401) {
            this.error.set(
              'Tu sesión ha expirado. Inicia sesión nuevamente.'
            );
            return;
          }

          if (err.status === 403) {
            this.error.set(
              'No tienes permisos para crear restaurantes.'
            );
            return;
          }

          if (err.status === 422) {
            const errores = err.error?.errors;

            if (errores?.slug) {
              this.error.set(
                'Ese slug ya está registrado o no tiene un formato válido. Prueba con otro.'
              );
            } else if (errores?.correo_administrador) {
              this.error.set(
                'Ese correo de administrador ya está registrado o no es válido.'
              );
            } else {
              const primerError = errores
                ? Object.values(errores).flat()[0]
                : null;

              this.error.set(
                typeof primerError === 'string'
                  ? primerError
                  : 'Revisa los datos del formulario e inténtalo nuevamente.'
              );
            }

            return;
          }

          console.error('Error al crear restaurante:', err);

          this.error.set(
            'No se pudo crear el restaurante. Comprueba la conexión e inténtalo nuevamente.'
          );
        }
      });
  }
}
