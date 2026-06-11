import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { ForoService } from '../services/foro';

export const publicacionRegistroGuard: CanActivateFn = (route) => {
  const router = inject(Router);
  const foroService = inject(ForoService);
  const publicacionId = Number(route.paramMap.get('publicacion_id'));

  if (!publicacionId) {
    router.navigate(['/home']);
    return false;
  }

  return foroService.verificarRegistroPublicacion(publicacionId).pipe(
    map((res) => {
      if (res?.registrado) {
        return true;
      }

      router.navigate(['/home']);
      return false;
    }),
    catchError(() => {
      router.navigate(['/home']);
      return of(false);
    })
  );
};
