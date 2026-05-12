import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

export const authGuard: CanActivateFn = () => {

  const router = inject(Router);

  const usuario = localStorage.getItem('usuario');
  const token = localStorage.getItem('token');

  if (usuario && token) {
    return true;
  }

  localStorage.removeItem('usuario');
  localStorage.removeItem('token');
  router.navigate(['/login']);
  return false;
};
