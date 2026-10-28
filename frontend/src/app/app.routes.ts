import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'restaurante-prueba'
  },

  // Superadministrador
  {
    path: 'admin',
    loadComponent: () =>
      import('./admin/layout/admin-layout/admin-layout')
        .then(m => m.AdminLayout),
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'dashboard'
      },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./admin/pages/dashboard/dashboard')
            .then(m => m.Dashboard)
      },
      {
        path: 'restaurantes',
        loadComponent: () =>
          import('./admin/pages/restaurantes/restaurantes')
            .then(m => m.Restaurantes)
      },
      {
        path: 'restaurantes/nuevo',
        loadComponent: () =>
          import('./admin/pages/restaurantes/nuevo-restaurante/nuevo-restaurante')
            .then(m => m.NuevoRestaurante)
      }
    ]
  },

  // Menú público
  {
    path: ':slug',
    loadComponent: () =>
      import('./public/menu/menu')
        .then(m => m.MenuComponent)
  },

  {
    path: '**',
    redirectTo: 'restaurante-prueba'
  }
];
