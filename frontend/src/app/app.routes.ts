
import { Routes } from '@angular/router';
import { superadminGuard } from './auth/superadmin-guard';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'restaurante-prueba'
  },

  // Inicio de sesión del superadministrador
  {
    path: 'login',
    loadComponent: () =>
      import('./auth/login/login')
        .then(m => m.LoginComponent)
  },

  // Panel del superadministrador
  {
    path: 'admin',
    canActivate: [superadminGuard],
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
