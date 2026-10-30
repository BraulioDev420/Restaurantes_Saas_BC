import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import {
    CategoriasService,
    Categoria,
    DatosCategoria
} from '../../../admin/services/categorias';
import { Auth } from '../../../auth/auth';
import { FormsModule } from '@angular/forms';

@Component({
    selector: 'app-categorias',
    standalone: true,
    imports: [CommonModule,FormsModule],
    templateUrl: './categorias.html',
    styleUrl: './categorias.css',
})
export class Categorias implements OnInit {

    idRestaurante = 0;
    rolUsuario = '';

    categorias = signal<Categoria[]>([]);
    cargando = signal(false);
    guardando = signal(false);
    error = signal('');
    mensaje = signal('');

    mostrarFormulario = signal(false);
    categoriaEditando = signal<number | null>(null);

    nombre = signal('');
    descripcion = signal('');
    ordenMenu = signal(0);
    activo = signal(true);

    constructor(
        private route: ActivatedRoute,
        private categoriasService: CategoriasService,
        private auth: Auth
    ) { }

    ngOnInit(): void {
        const id = Number(
            this.route.parent?.snapshot.paramMap.get('id_restaurante')
        );

        if (!id || !Number.isInteger(id)) {
            this.error.set('No se pudo identificar el restaurante.');
            return;
        }

        this.idRestaurante = id;

        const restaurante = this.auth.obtenerRestaurantes().find(
            item => item.id_restaurante === id
        );

        this.rolUsuario = restaurante?.rol ?? '';

        this.cargarCategorias();
    }

    get esAdministrador(): boolean {
        return this.rolUsuario.toLowerCase() === 'administrador';
    }

    cargarCategorias(): void {
        this.cargando.set(true);
        this.error.set('');

        this.categoriasService.listar(this.idRestaurante).subscribe({
            next: respuesta => {
                this.categorias.set(respuesta.categorias);
                this.cargando.set(false);
            },
            error: error => {
                this.error.set(
                    error.status === 403
                        ? 'No tienes permisos para consultar estas categorías.'
                        : 'No fue posible cargar las categorías.'
                );
                this.cargando.set(false);
            }
        });
    }

    abrirFormulario(): void {
        if (!this.esAdministrador) return;

        this.limpiarFormulario();
        this.categoriaEditando.set(null);
        this.mostrarFormulario.set(true);
    }

    editarCategoria(categoria: Categoria): void {
        if (!this.esAdministrador) return;

        this.categoriaEditando.set(categoria.id_categoria);
        this.nombre.set(categoria.nombre);
        this.descripcion.set(categoria.descripcion ?? '');
        this.ordenMenu.set(categoria.orden_menu);
        this.activo.set(categoria.activo);
        this.mostrarFormulario.set(true);
    }

    guardarCategoria(): void {
        if (!this.esAdministrador || this.guardando()) return;

        const nombre = this.nombre().trim();

        if (!nombre) {
            this.error.set('El nombre de la categoría es obligatorio.');
            return;
        }

        const datos: DatosCategoria = {
            nombre,
            descripcion: this.descripcion().trim() || null,
            orden_menu: this.ordenMenu(),
            activo: this.activo()
        };

        this.guardando.set(true);
        this.error.set('');
        this.mensaje.set('');

        const idCategoria = this.categoriaEditando();

        const peticion = idCategoria
            ? this.categoriasService.actualizar(
                this.idRestaurante,
                idCategoria,
                datos
            )
            : this.categoriasService.crear(this.idRestaurante, datos);

        peticion.subscribe({
            next: respuesta => {
                this.mensaje.set(
                    respuesta.mensaje ??
                    (idCategoria
                        ? 'Categoría actualizada correctamente.'
                        : 'Categoría creada correctamente.')
                );

                this.guardando.set(false);
                this.mostrarFormulario.set(false);
                this.limpiarFormulario();
                this.cargarCategorias();
            },
            error: error => {
                this.guardando.set(false);

                if (error.status === 422) {
                    this.error.set(
                        error.error?.errors?.nombre?.[0] ??
                        'Verifica los datos ingresados. Puede que el nombre ya exista.'
                    );
                } else if (error.status === 403) {
                    this.error.set('No tienes permisos para realizar esta operación.');
                } else {
                    this.error.set('No fue posible guardar la categoría.');
                }
            }
        });
    }

    cambiarEstado(categoria: Categoria): void {
        if (!this.esAdministrador) return;

        const nuevoEstado = !categoria.activo;

        const datos: Partial<DatosCategoria> = {
            activo: nuevoEstado
        };

        this.error.set('');
        this.mensaje.set('');

        this.categoriasService.actualizar(
            this.idRestaurante,
            categoria.id_categoria,
            datos
        ).subscribe({
            next: () => {
                this.mensaje.set(
                    nuevoEstado
                        ? 'Categoría activada correctamente.'
                        : 'Categoría desactivada correctamente.'
                );

                this.cargarCategorias();
            },
            error: error => {
                this.error.set(
                    error.status === 403
                        ? 'No tienes permisos para cambiar el estado.'
                        : 'No fue posible cambiar el estado de la categoría.'
                );
            }
        });
    }

    cancelarFormulario(): void {
        this.mostrarFormulario.set(false);
        this.limpiarFormulario();
        this.error.set('');
    }

    limpiarFormulario(): void {
        this.nombre.set('');
        this.descripcion.set('');
        this.ordenMenu.set(0);
        this.activo.set(true);
    }
}
