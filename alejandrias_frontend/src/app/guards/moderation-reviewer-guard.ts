import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { obtenerTokenSesion } from '../services/auth-session';

export const moderationReviewerGuard: CanActivateFn = () => {
  const router = inject(Router);
  const usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
  const token = obtenerTokenSesion();

  if (token && ['admin', 'lider'].includes(usuario?.usuario_rol)) {
    return true;
  }

  router.navigate(['/perfil']);
  return false;
};
