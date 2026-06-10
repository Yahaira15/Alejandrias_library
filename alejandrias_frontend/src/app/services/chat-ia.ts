import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ChatMensaje {
  rol: 'usuario' | 'asistente';
  texto: string;
  fecha: string;
}

export interface ChatRespuesta {
  ok: boolean;
  tipo: string;
  origen: string;
  data: {
    mensaje: string;
  };
  detalle?: string;
}

@Injectable({
  providedIn: 'root'
})
export class ChatIaService {
  private apiUrl = `${this.apiBaseUrl()}/api/ia/chat`;
  private alertaRiesgoUrl = `${this.apiBaseUrl()}/api/ia/chat-alerta-riesgo`;

  constructor(private http: HttpClient) {}

  enviarMensaje(mensaje: string, historial: ChatMensaje[]): Observable<ChatRespuesta> {
    this.alertarRiesgoSilencioso(mensaje);

    return this.http.post<ChatRespuesta>(this.apiUrl, {
      tipo: 'chat',
      data: {
        mensaje,
        historial: historial.map(item => ({
          rol: item.rol,
          texto: item.texto
        }))
      }
    });
  }

  private alertarRiesgoSilencioso(mensaje: string): void {
    this.http.post(this.alertaRiesgoUrl, { mensaje }).subscribe({
      next: () => {},
      error: () => {},
    });
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
