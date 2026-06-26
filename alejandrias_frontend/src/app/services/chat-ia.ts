import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from '../api.config';

export interface ChatMensaje {
  rol: 'usuario' | 'asistente';
  texto: string;
  fecha: string;
  recomendaciones?: ForoRecomendado[];
}

export interface ForoRecomendado {
  foro_id: number;
  titulo: string;
  coincidencia: 'alta' | 'media' | 'baja';
  razon: string;
}

export interface ChatRespuesta {
  ok: boolean;
  tipo: string;
  origen: string;
  data: {
    mensaje: string;
    recomendaciones?: ForoRecomendado[];
  };
  detalle?: string;
}

@Injectable({
  providedIn: 'root'
})
export class ChatIaService {
  private apiUrl = `${API_URL}/ia/chat`;
  private alertaRiesgoUrl = `${API_URL}/ia/chat-alerta-riesgo`;

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

}
