
import { Routes } from '@angular/router';
import { superadminGuard } from './auth/superadmin-guard';
import { restaurantGuard } from './auth/restaurant-guard';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'restaurante-prueba'
  },

  // Inicio de sesión
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
      },
      {
        path: 'restaurantes/:id/editar',
        loadComponent: () =>
          import('./admin/pages/restaurantes/editar-restaurante/editar-restaurante')
            .then(m => m.EditarRestaurante)
      }
    ]
  },

  // Panel de administración del restaurante
  {
    path: 'panel/:id_restaurante',
    canActivate: [restaurantGuard],
    loadComponent: () =>
      import('./panel/layout/panel-layout/panel-layout')
        .then(m => m.PanelLayout),
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('./panel/pages/dashboard/dashboard')
            .then(m => m.Dashboard)
      },
      {
      path: 'categorias',
      loadComponent: () =>
        import('./panel/pages/categorias/categorias')
          .then(m => m.Categorias)
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
