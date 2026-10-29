import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { Auth } from './auth';

export const superadminGuard: CanActivateFn = () => {
  const auth = inject(Auth);
  const router = inject(Router);

  if (!auth.obtenerToken()) {
    return router.createUrlTree(['/login']);
  }

  return auth.verificarSuperadministrador().pipe(
    map((respuesta) => {
      if (respuesta.usuario.es_superadministrador) {
        return true;
      }

      auth.limpiarSesionLocal();
      return router.createUrlTree(['/login']);
    }),
    catchError(() => {
      auth.limpiarSesionLocal();
      return of(router.createUrlTree(['/login']));
    })
  );
};