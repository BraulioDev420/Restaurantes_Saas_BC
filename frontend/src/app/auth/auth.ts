import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

export interface UsuarioAutenticado {
  id_usuario: number;
  nombre: string;
  correo: string;
  es_superadministrador: boolean;
}

export interface RestauranteUsuario {
  id_restaurante: number;
  nombre: string;
  slug: string;
  rol: string;
}

export interface RespuestaLogin {
  mensaje: string;
  token: string;
  tipo_token: string;
  usuario: UsuarioAutenticado;
  restaurantes: RestauranteUsuario[];
}

export interface RespuestaSuperadministrador {
  usuario: UsuarioAutenticado & {
    es_superadministrador: boolean;
  };
}

@Injectable({ providedIn: 'root' })
export class Auth {
  private readonly apiUrl = '/api';
  private readonly tokenKey = 'saborea_token';
  private readonly usuarioKey = 'saborea_usuario';
  private readonly restaurantesKey = 'saborea_restaurantes';

  constructor(private http: HttpClient) { }

  iniciarSesion(correo: string, contrasena: string): Observable<RespuestaLogin> {
    return this.http.post<RespuestaLogin>(`${this.apiUrl}/login`, {
      correo,
      contrasena
    }).pipe(
      tap((respuesta) => {
        localStorage.setItem(this.tokenKey, respuesta.token);
        localStorage.setItem(this.usuarioKey, JSON.stringify(respuesta.usuario));
        localStorage.setItem(this.restaurantesKey, JSON.stringify(respuesta.restaurantes));
      })
    );
  }

  obtenerToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  obtenerUsuario(): UsuarioAutenticado | null {
    const usuario = localStorage.getItem(this.usuarioKey);

    if (!usuario) return null;

    try {
      return JSON.parse(usuario) as UsuarioAutenticado;
    } catch {
      return null;
    }
  }

  obtenerRestaurantes(): RestauranteUsuario[] {
    const restaurantes = localStorage.getItem(this.restaurantesKey);

    if (!restaurantes) return [];

    try {
      return JSON.parse(restaurantes) as RestauranteUsuario[];
    } catch {
      return [];
    }
  }

  verificarSuperadministrador(): Observable<RespuestaSuperadministrador> {
    return this.http.get<RespuestaSuperadministrador>(`${this.apiUrl}/admin/yo`);
  }


  cerrarSesion(): void {
    const token = this.obtenerToken();

    // Limpiar la sesión local inmediatamente.
    this.limpiarSesion();

    // Revocar el token en Laravel.
    if (token) {
      this.http.post(`${this.apiUrl}/logout`, {}, {
        headers: { Authorization: `Bearer ${token}` }
      }).subscribe({
        error: (error) => {
          console.error('No se pudo revocar el token en el servidor:', error);
        }
      });
    }
  }


  private limpiarSesion(): void {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.usuarioKey);
    localStorage.removeItem(this.restaurantesKey);
  }

  limpiarSesionLocal(): void {
    this.limpiarSesion();
  }
}
