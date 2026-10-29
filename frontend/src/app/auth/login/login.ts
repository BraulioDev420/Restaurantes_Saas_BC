import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Auth } from '../auth';
import { Component, signal } from '@angular/core';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.html',
  styleUrl: './login.css'
})
export class LoginComponent {
  correo = '';
  contrasena = '';
  error = signal('');
  cargando = signal(false);

  constructor(
    private auth: Auth,
    private router: Router
  ) {}

  iniciarSesion(): void {
    this.error.set('');

    if (!this.correo.trim() || !this.contrasena) {
      this.error.set('Ingresa tu correo y contraseña.');
      return;
    }

    this.cargando.set(true);

    this.auth.iniciarSesion(this.correo.trim(), this.contrasena).subscribe({
      next: () => {
        this.auth.verificarSuperadministrador().subscribe({
          next: (respuesta) => {
            this.cargando.set(false);

            if (respuesta.usuario.es_superadministrador) {
              this.router.navigate(['/admin/dashboard']);
            } else {
              this.auth.limpiarSesionLocal();
              this.error.set('Esta cuenta no tiene permisos de superadministrador.');
            }
          },
          error: () => {
            this.auth.limpiarSesionLocal();
            this.cargando.set(false);
            this.error.set('No fue posible verificar los permisos de esta cuenta.');
          }
        });
      },
      error: (error) => {
        this.cargando.set(false);

        if (error.status === 401 || error.status === 422) {
          this.error.set('Correo o contraseña incorrectos.');
        } else {
          this.error.set('No se pudo conectar con el servidor. Inténtalo nuevamente.');
        }
      }
    });
  }
}