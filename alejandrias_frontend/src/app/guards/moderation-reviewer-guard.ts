import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

export const moderationReviewerGuard: CanActivateFn = () => {
  const router = inject(Router);
  const usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
  const token = localStorage.getItem('token');

  if (token && ['admin', 'lider'].includes(usuario?.usuario_rol)) {
    return true;
  }

  router.navigate(['/perfil']);
  return false;
};
