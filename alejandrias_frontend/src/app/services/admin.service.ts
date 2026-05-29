import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private baseUrl = 'http://127.0.0.1:8000';
  private apiUrl = `${this.baseUrl}/api/admin`;

  constructor(private http: HttpClient) {}

  listar(recurso: string): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/${recurso}`);
  }

  crear(recurso: string, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/${recurso}`, data);
  }

  actualizar(recurso: string, id: number, data: any): Observable<any> {
    if (data instanceof FormData) {
      data.append('_method', 'PUT');
      return this.http.post(`${this.apiUrl}/${recurso}/${id}`, data);
    }

    return this.http.put(`${this.apiUrl}/${recurso}/${id}`, data);
  }

  eliminar(recurso: string, id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${recurso}/${id}`);
  }

  sancionarReporte(reporteId: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/reportes/${reporteId}/sancionar`, data);
  }

  listarModeracion(params: Record<string, string | number | boolean> = {}): Observable<any[]> {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      query.set(key, String(value));
    });

    const suffix = query.toString() ? `?${query.toString()}` : '';
    return this.http.get<any[]>(`${this.apiUrl}/moderacion${suffix}`);
  }

  aprobarModeracion(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/moderacion/${id}/aprobar`, {});
  }

  rechazarModeracion(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/moderacion/${id}/rechazar`, {});
  }

  listarSubcategoriasCategoria(categoriaId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/categorias/${categoriaId}/subcategorias`);
  }

  crearSubcategoria(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/subcategorias`, data);
  }

  actualizarSubcategoria(id: number, data: any): Observable<any> {
    if (data instanceof FormData) {
      data.append('_method', 'PUT');
      return this.http.post(`${this.apiUrl}/subcategorias/${id}`, data);
    }

    return this.http.put(`${this.apiUrl}/subcategorias/${id}`, data);
  }

  eliminarSubcategoria(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/subcategorias/${id}`);
  }

  resolverUrlImagen(url: string | null | undefined): string {
    if (!url) return '';

    if (/^(blob:|data:|https?:\/\/)/i.test(url)) {
      return url;
    }

    return `${this.baseUrl}${url.startsWith('/') ? url : `/${url}`}`;
  }
}
