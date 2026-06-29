import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { obtenerTokenSesion } from '../services/auth-session';

export const adminGuard: CanActivateFn = () => {
  const router = inject(Router);
  const usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
  const token = obtenerTokenSesion();

  if (token && usuario?.usuario_rol === 'admin') {
    return true;
  }

  router.navigate(['/perfil']);
  return false;
};
