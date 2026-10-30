import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Auth } from '../../../auth/auth';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class Dashboard implements OnInit {
  idRestaurante = '';
  nombreRestaurante = 'Mi restaurante';
  nombreUsuario = 'Usuario';

  constructor(
    private route: ActivatedRoute,
    private auth: Auth
  ) {}

  ngOnInit(): void {
    this.route.parent?.paramMap.subscribe(params => {
      this.idRestaurante = params.get('id_restaurante') ?? '';

      const restaurante = this.auth.obtenerRestaurantes().find(
        item => item.id_restaurante === Number(this.idRestaurante)
      );

      this.nombreRestaurante = restaurante?.nombre ?? 'Mi restaurante';

      const usuario = this.auth.obtenerUsuario();
      this.nombreUsuario = usuario?.nombre ?? 'Usuario';
    });
  }
}