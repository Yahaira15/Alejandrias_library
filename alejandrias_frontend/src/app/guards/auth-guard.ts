import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { obtenerTokenSesion } from '../services/auth-session';

export const authGuard: CanActivateFn = () => {

  const router = inject(Router);

  const token = obtenerTokenSesion();

  if (token) {
    return true;
  }

  router.navigate(['/login']);
  return false;
};
