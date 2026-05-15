import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private apiUrl = 'http://127.0.0.1:8000/api/admin';

  constructor(private http: HttpClient) {}

  listar(recurso: string): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/${recurso}`);
  }

  crear(recurso: string, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/${recurso}`, data);
  }

  actualizar(recurso: string, id: number, data: any): Observable<any> {
    return this.http.put(`${this.apiUrl}/${recurso}/${id}`, data);
  }

  eliminar(recurso: string, id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${recurso}/${id}`);
  }
}
