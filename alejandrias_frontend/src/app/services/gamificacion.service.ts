import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { API_URL } from '../api.config';

export interface RachaUsuario {
  dias_consecutivos: number;
  ultima_fecha: string | null;
  mejor_racha: number;
  recompensa_reclamada: boolean;
  xp_obtenida_hoy: number;
  mensaje: string;
  calendario: Array<{ fecha: string; activo: boolean }>;
  siguiente_recompensa: { dia: number; xp: number };
}

export interface MisionUsuario {
  usuario_mision_id: number;
  slug: string;
  titulo: string;
  descripcion: string;
  tipo: string;
  objetivo: number;
  progreso: number;
  xp_recompensa: number;
  puntos_recompensa: number;
  insignia_temporal: string | null;
  completada: boolean;
  reclamada: boolean;
}

export interface RankingUsuario {
  posicion: number;
  usuario_id: number;
  nombre: string;
  apodo: string;
  avatar: string | null;
  nivel: number;
  xp: number;
  puntos: number;
  total: number;
  medalla: 'oro' | 'plata' | 'bronce' | null;
}

@Injectable({ providedIn: 'root' })
export class GamificacionService {
  private apiUrl = API_URL;

  constructor(private http: HttpClient) {}

  getPanel() {
    return this.http.get<any>(`${this.apiUrl}/gamificacion/panel`);
  }

  registrarRacha() {
    return this.http.get<{ racha: RachaUsuario | null; misiones: MisionUsuario[] }>(`${this.apiUrl}/gamificacion/racha`);
  }

  reclamarRacha() {
    return this.http.post<any>(`${this.apiUrl}/gamificacion/racha/reclamar`, {});
  }

  reclamarMision(usuarioMisionId: number) {
    return this.http.post<any>(`${this.apiUrl}/gamificacion/misiones/${usuarioMisionId}/reclamar`, {});
  }

  getRanking(tipo: 'xp' | 'puntos' | 'publicaciones' | 'comentarios', periodo: 'global' | 'semanal') {
    const params = new HttpParams().set('tipo', tipo).set('periodo', periodo);
    return this.http.get<RankingUsuario[]>(`${this.apiUrl}/gamificacion/ranking`, { params });
  }
}
