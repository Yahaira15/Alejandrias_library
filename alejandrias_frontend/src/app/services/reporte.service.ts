import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ReportePayload {
  reporte_tipo: 'publicacion' | 'comentario' | 'usuario' | 'foro';
  reporte_referencia_id: number;
  reporte_motivo: string;
  reporte_descripcion?: string;
  reporte_prioridad?: 'baja' | 'media' | 'alta' | 'critica';
}

@Injectable({ providedIn: 'root' })
export class ReporteService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  crearReporte(payload: ReportePayload): Observable<any> {
    return this.http.post(`${this.apiUrl}/reportes`, payload);
  }
}
