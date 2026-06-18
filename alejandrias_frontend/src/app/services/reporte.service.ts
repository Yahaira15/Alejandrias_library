import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from '../api.config';

export interface ReportePayload {
  reporte_tipo: 'publicacion' | 'comentario' | 'usuario' | 'foro';
  reporte_referencia_id: number;
  reporte_motivo: string;
  reporte_descripcion?: string;
  reporte_prioridad?: 'baja' | 'media' | 'alta' | 'critica';
}

@Injectable({ providedIn: 'root' })
export class ReporteService {
  private apiUrl = API_URL;

  constructor(private http: HttpClient) {}

  crearReporte(payload: ReportePayload): Observable<any> {
    return this.http.post(`${this.apiUrl}/reportes`, payload);
  }
}
