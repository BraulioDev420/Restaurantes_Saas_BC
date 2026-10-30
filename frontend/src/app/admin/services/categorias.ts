
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Categoria {
  id_categoria: number;
  id_restaurante: number;
  nombre: string;
  descripcion: string | null;
  orden_menu: number;
  activo: boolean;
  creado_en?: string;
  actualizado_en?: string;
}

export interface RespuestaCategorias {
  categorias: Categoria[];
}

export interface RespuestaCategoria {
  categoria: Categoria;
  mensaje?: string;
}

export interface DatosCategoria {
  nombre: string;
  descripcion?: string | null;
  orden_menu?: number;
  activo?: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class CategoriasService {
  private readonly apiUrl = '/api/restaurantes';

  constructor(private http: HttpClient) {}

  listar(idRestaurante: number): Observable<RespuestaCategorias> {
    return this.http.get<RespuestaCategorias>(
      `${this.apiUrl}/${idRestaurante}/categorias`
    );
  }

  obtener(
    idRestaurante: number,
    idCategoria: number
  ): Observable<RespuestaCategoria> {
    return this.http.get<RespuestaCategoria>(
      `${this.apiUrl}/${idRestaurante}/categorias/${idCategoria}`
    );
  }

  crear(
    idRestaurante: number,
    datos: DatosCategoria
  ): Observable<RespuestaCategoria> {
    return this.http.post<RespuestaCategoria>(
      `${this.apiUrl}/${idRestaurante}/categorias`,
      datos
    );
  }

  actualizar(
    idRestaurante: number,
    idCategoria: number,
    datos: Partial<DatosCategoria>
  ): Observable<RespuestaCategoria> {
    return this.http.put<RespuestaCategoria>(
      `${this.apiUrl}/${idRestaurante}/categorias/${idCategoria}`,
      datos
    );
  }

  desactivar(
    idRestaurante: number,
    idCategoria: number
  ): Observable<{ mensaje: string }> {
    return this.http.delete<{ mensaje: string }>(
      `${this.apiUrl}/${idRestaurante}/categorias/${idCategoria}`
    );
  }
}
