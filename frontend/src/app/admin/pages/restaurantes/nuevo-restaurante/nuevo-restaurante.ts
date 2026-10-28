
import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-nuevo-restaurante',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './nuevo-restaurante.html',
  styleUrl: './nuevo-restaurante.css'
})
export class NuevoRestaurante {
  guardando = false;
  mensaje = '';
  error = '';

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
    this.mensaje = '';
    this.error = '';

    if (this.restaurante.contrasena.length < 8) {
      this.error = 'La contraseña debe tener al menos 8 caracteres.';
      return;
    }

    if (this.restaurante.contrasena !== this.restaurante.confirmarContrasena) {
      this.error = 'Las contraseñas no coinciden.';
      return;
    }

    // Temporal: todavía no se envían datos al backend.
    this.mensaje =
      'Formulario validado. El registro real se habilitará al conectar Laravel.';
  }
}
