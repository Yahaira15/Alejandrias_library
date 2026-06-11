import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class NotificacionService {

  private apiUrl = `${this.apiBaseUrl()}/api`;

  constructor(private http: HttpClient) {}

  // 🔔 Obtener notificaciones
  getNotificaciones(): Observable<any> {
    return this.http.get(`${this.apiUrl}/notificaciones`);
  }

  // 🔴 Contador no leídas
  getCantidadNoLeidas(): Observable<any> {
    return this.http.get(`${this.apiUrl}/notificaciones-contador`);
  }

  // ✅ Marcar como leída
  marcarLeida(id: number): Observable<any> {
    return this.http.put(
      `${this.apiUrl}/notificaciones/${id}/leer`,
      {}
    );
  }
  marcarComoLeida(id: number): Observable<any> {

  return this.http.put(
    `${this.apiUrl}/notificaciones/${id}/leer`,
    {}
  );
}

  resolverDestino(notificacion: any): string {
    const url = this.normalizarUrl(notificacion?.notificacion_url || '');
    if (url) return url;

    const referenciaId = notificacion?.notificacion_referencia_id;
    const tipo = String(notificacion?.notificacion_tipo || '');

    if (tipo.startsWith('alerta_ia_')) {
      return referenciaId ? `/admin/moderacion?moderacion_id=${referenciaId}` : '/admin/moderacion';
    }

    switch (tipo) {
      case 'registro_foro':
      case 'nuevo_miembro':
        return referenciaId ? `/foros/${referenciaId}` : '/home';
      case 'nueva_publicacion':
      case 'nuevo_comentario':
      case 'lider_publicacion_relevante':
      case 'lider_comentario_relevante':
        return referenciaId ? `/publicaciones/${referenciaId}` : '/home';
      case 'nuevo_reporte':
        return '/admin/reportes';
      default:
        return '/home';
    }
  }

  private normalizarUrl(url: string): string {
    const limpia = String(url || '').trim();
    if (!limpia) return '';

    const publicacionVieja = limpia.match(/^\/foro\/(\d+)\/publicacion\/(\d+)(#.*)?$/);
    if (publicacionVieja) {
      return `/publicaciones/${publicacionVieja[2]}${publicacionVieja[3] || ''}`;
    }

    const foroViejo = limpia.match(/^\/foro\/(\d+)$/);
    if (foroViejo) {
      return `/foros/${foroViejo[1]}`;
    }

    return limpia;
  }

  private apiBaseUrl(): string {
    const localBase = 'http://127.0.0.1:8000';

    if (typeof window === 'undefined') {
      return localBase;
    }

    const hostname = window.location.hostname;

    if (!hostname || hostname === 'localhost' || hostname === '127.0.0.1') {
      return localBase;
    }

    return `${window.location.protocol}//${hostname}:8000`;
  }
}
