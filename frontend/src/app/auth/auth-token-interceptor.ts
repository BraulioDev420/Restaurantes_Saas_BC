
import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Auth } from './auth';

export const authTokenInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth);
  const token = auth.obtenerToken();

  // Solo adjuntamos el token a peticiones de nuestra API.
  const esPeticionApi = req.url.startsWith('/api/');

  if (!token || !esPeticionApi || req.url.endsWith('/api/login')) {
    return next(req);
  }

  const solicitudAutenticada = req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`,
    },
  });

  return next(solicitudAutenticada);
};
