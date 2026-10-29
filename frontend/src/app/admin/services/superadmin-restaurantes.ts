import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface RestauranteSuperadmin {
  id: number;
  nombre: string;
  slug: string;
  administrador: string | null;
  correo: string | null;
  estado: string;
  fecha: string | null;
}

export interface CrearRestauranteSuperadmin {
  nombre: string;
  slug: string;
  descripcion?: string;
  telefono?: string;
  correo?: string;
  direccion?: string;
  nombre_administrador: string;
  correo_administrador: string;
  contrasena: string;
}

export interface RespuestaRestaurantes {
  restaurantes: RestauranteSuperadmin[];
}

export interface RespuestaCrearRestaurante {
  mensaje: string;
  restaurante: RestauranteSuperadmin;
}

@Injectable({
  providedIn: 'root',
})
export class SuperadminRestaurantes {
  private readonly apiUrl = '/api/admin/restaurantes';

  constructor(private http: HttpClient) {}

  listar(): Observable<RespuestaRestaurantes> {
    return this.http.get<RespuestaRestaurantes>(this.apiUrl);
  }

  crear(
    datos: CrearRestauranteSuperadmin
  ): Observable<RespuestaCrearRestaurante> {
    return this.http.post<RespuestaCrearRestaurante>(
      this.apiUrl,
      datos
    );
  }
}