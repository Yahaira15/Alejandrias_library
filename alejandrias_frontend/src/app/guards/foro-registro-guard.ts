import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { ForoService } from '../services/foro';

export const foroRegistroGuard: CanActivateFn = (route) => {
  const router = inject(Router);
  const foroService = inject(ForoService);
  const foroId = Number(route.paramMap.get('foro_id'));

  if (!foroId) {
    router.navigate(['/foros']);
    return false;
  }

  return foroService.verificarRegistroForo(foroId).pipe(
    map((res) => {
      if (res?.registrado) {
        return true;
      }

      router.navigate(['/foros']);
      return false;
    }),
    catchError(() => {
      router.navigate(['/foros']);
      return of(false);
    })
  );
};
