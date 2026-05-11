import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class NotificacionService {

  private apiUrl = 'http://127.0.0.1:8000/api';

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
}