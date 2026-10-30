import { Component } from '@angular/core';
import { ActivatedRoute, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Auth } from '../../../auth/auth';


@Component({
  selector: 'app-panel-layout',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './panel-layout.html',
  styleUrl: './panel-layout.css'
})
export class PanelLayout {
  idRestaurante: string;
  nombreRestaurante = 'Mi restaurante';
  nombreUsuario = 'Usuario';
  rolUsuario = '';

  constructor(
    private route: ActivatedRoute,
    private auth: Auth,
    private router: Router
  ) {
    this.idRestaurante =
      this.route.snapshot.paramMap.get('id_restaurante') ?? '';

    const usuario = this.auth.obtenerUsuario();
    this.nombreUsuario = usuario?.nombre ?? 'Usuario';

    const restaurante = this.auth.obtenerRestaurantes().find(
      item => item.id_restaurante === Number(this.idRestaurante)
    );

    this.nombreRestaurante = restaurante?.nombre ?? 'Mi restaurante';
    this.rolUsuario = restaurante?.rol ?? '';
  }

  cerrarSesion(): void {
    this.auth.cerrarSesion();
    this.router.navigate(['/login']);
  }
}