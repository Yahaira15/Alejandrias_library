import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

function getApiPath(url: string): string | null {
  try {
    const baseUrl = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const parsedUrl = new URL(url, baseUrl);

    if (parsedUrl.port === '8000' && parsedUrl.pathname.startsWith('/api')) {
      return parsedUrl.pathname.replace(/^\/api/, '') || '/';
    }
  } catch {
    return null;
  }

  return null;
}

function isPublicApiRequest(method: string, path: string): boolean {
  if (method === 'POST' && ['/login', '/register'].includes(path)) {
    return true;
  }

  if (method === 'GET' && path.startsWith('/verificar-apodo/')) {
    return true;
  }

  if (method !== 'GET') {
    return false;
  }

  return (
    path === '/foros-publicos'
    || path === '/foros'
    || /^\/foros\/\d+$/.test(path)
    || /^\/foros\/\d+\/publicaciones$/.test(path)
    || path === '/categorias'
    || /^\/categorias\/\d+(\/foros)?$/.test(path)
    || /^\/publicaciones\/\d+$/.test(path)
    || /^\/publicaciones\/\d+\/comentarios$/.test(path)
    || /^\/comentarios\/\d+$/.test(path)
    || /^\/comentarios\/\d+\/respuestas$/.test(path)
  );
}

function isAuthenticationFailure(error: any): boolean {
  const body = error?.error;
  const message = String(
    body?.error
    || body?.message
    || body?.mensaje
    || ''
  ).toLowerCase();

  return (
    message.includes('no autenticado')
    || message.includes('unauthenticated')
    || message.includes('token')
  );
}

export const authInterceptor: HttpInterceptorFn = (req, next) => {

  const router = inject(Router);
  const token = localStorage.getItem('token');
  const apiPath = getApiPath(req.url);
  const isBackendApiRequest = apiPath !== null;
  const isProtectedApiRequest = isBackendApiRequest && !isPublicApiRequest(req.method, apiPath);

  let cloned = req;

  if (token && isBackendApiRequest) {
    cloned = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`
      }
    });
  }

  return next(cloned).pipe(
    catchError((error) => {

      // 🔥 Si el token falla
      if (error.status === 401 && token && isProtectedApiRequest && isAuthenticationFailure(error)) {
        console.warn('Solicitud autenticada rechazada:', apiPath);
      }

      if (error.status === 403 && apiPath?.startsWith('/admin')) {
        router.navigate(['/perfil']);
      }

      return throwError(() => error);
    })
  );
};
