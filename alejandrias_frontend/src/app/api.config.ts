const LOCAL_API_BASE_URL = 'http://127.0.0.1:8000';
const PRODUCTION_API_BASE_URL = 'https://alejandriasbackend-production.up.railway.app';

function resolveApiBaseUrl(): string {
  if (typeof window === 'undefined') {
    return PRODUCTION_API_BASE_URL;
  }

  return ['localhost', '127.0.0.1'].includes(window.location.hostname)
    ? LOCAL_API_BASE_URL
    : PRODUCTION_API_BASE_URL;
}

export const API_BASE_URL = resolveApiBaseUrl();
export const API_URL = `${API_BASE_URL}/api`;
