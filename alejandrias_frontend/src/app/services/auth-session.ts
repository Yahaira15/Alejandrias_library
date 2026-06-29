import { API_URL } from '../api.config';

const TOKEN_KEY = 'token';

function getStorage(name: 'localStorage' | 'sessionStorage'): Storage | null {
  try {
    return typeof window !== 'undefined' ? window[name] : null;
  } catch {
    return null;
  }
}

export function guardarTokenSesion(token: string): void {
  getStorage('sessionStorage')?.setItem(TOKEN_KEY, token);
  getStorage('localStorage')?.removeItem(TOKEN_KEY);
}

export function obtenerTokenSesion(): string | null {
  getStorage('localStorage')?.removeItem(TOKEN_KEY);
  return getStorage('sessionStorage')?.getItem(TOKEN_KEY) || null;
}

export function limpiarTokenSesion(): void {
  const token = getStorage('sessionStorage')?.getItem(TOKEN_KEY);

  if (token && typeof fetch !== 'undefined') {
    fetch(`${API_URL}/logout`, {
      method: 'POST',
      keepalive: true,
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    }).catch(() => undefined);
  }

  getStorage('sessionStorage')?.removeItem(TOKEN_KEY);
  getStorage('localStorage')?.removeItem(TOKEN_KEY);
}
