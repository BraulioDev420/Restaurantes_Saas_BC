import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Auth } from './auth';

export const restaurantGuard: CanActivateFn = (route) => {
  const auth = inject(Auth);
  const router = inject(Router);

  const token = auth.obtenerToken();
  const usuario = auth.obtenerUsuario();
  const idRestaurante = Number(route.paramMap.get('id_restaurante'));

  if (!token || !usuario) {
    return router.createUrlTree(['/login']);
  }

  if (usuario.es_superadministrador) {
    return router.createUrlTree(['/admin/dashboard']);
  }

  const restaurantes = auth.obtenerRestaurantes();

  const perteneceAlRestaurante = restaurantes.some(
    restaurante => restaurante.id_restaurante === idRestaurante
  );

  if (!idRestaurante || !perteneceAlRestaurante) {
    return router.createUrlTree(['/login']);
  }

  return true;
};
