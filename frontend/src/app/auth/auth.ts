
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

export interface UsuarioAutenticado {
  id_usuario: number;
  nombre: string;
  correo: string;
}

export interface RespuestaLogin {
  mensaje: string;
  token: string;
  tipo_token: string;
  usuario: UsuarioAutenticado;
}
export interface RespuestaSuperadministrador {
  usuario: UsuarioAutenticado & {
    es_superadministrador: boolean;
  };
}

@Injectable({
  providedIn: 'root',
})
export class Auth {
  private readonly apiUrl = '/api';
  private readonly tokenKey = 'saborea_token';
  private readonly usuarioKey = 'saborea_usuario';

  constructor(private http: HttpClient) { }

  iniciarSesion(
    correo: string,
    contrasena: string
  ): Observable<RespuestaLogin> {
    return this.http
      .post<RespuestaLogin>(`${this.apiUrl}/login`, {
        correo,
        contrasena,
      })

      .pipe(
        tap((respuesta) => {
          localStorage.setItem(this.tokenKey, respuesta.token);
          localStorage.setItem(
            this.usuarioKey,
            JSON.stringify(respuesta.usuario)
          );
        })
      );

  }


  obtenerToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  obtenerUsuario(): UsuarioAutenticado | null {
    const usuario = localStorage.getItem(this.usuarioKey);

    if (!usuario) {
      return null;
    }

    try {
      return JSON.parse(usuario) as UsuarioAutenticado;
    } catch {
      return null;
    }
  }

  verificarSuperadministrador(): Observable<RespuestaSuperadministrador> {
    return this.http.get<RespuestaSuperadministrador>(
      `${this.apiUrl}/admin/yo`
    );
  }

  cerrarSesion(): void {
    const token = this.obtenerToken();

    if (token) {
      this.http
        .post(
          `${this.apiUrl}/logout`,
          {},
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          }
        )
        .subscribe({
          complete: () => this.limpiarSesion(),
          error: () => this.limpiarSesion(),
        });

      return;
    }

    this.limpiarSesion();
  }

  private limpiarSesion(): void {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.usuarioKey);
  }
  limpiarSesionLocal(): void {
    this.limpiarSesion();
  }
}
