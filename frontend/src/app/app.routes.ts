
import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'restaurante-prueba'
  },
  {
    path: ':slug',
    loadComponent: () =>
      import('./app').then(m => m.App)
  },
  {
    path: '**',
    redirectTo: 'restaurante-prueba'
  }
];
