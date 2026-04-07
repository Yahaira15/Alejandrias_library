import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, tap } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ForoService {

  private apiUrl = 'http://127.0.0.1:8000/api';

  private cacheForos: any[] | null = null;
  private cacheCategorias: any[] | null = null;

  constructor(private http: HttpClient) {}

  // 🔹 CREAR
  crearForo(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros`, data).pipe(
      tap(() => this.cacheForos = null)
    );
  }

  // 🔹 CATEGORÍAS
  getCategorias(): Observable<any> {
    if (this.cacheCategorias) {
      return of(this.cacheCategorias);
    }

    return this.http.get(`${this.apiUrl}/categorias`).pipe(
      tap((res: any) => {
        this.cacheCategorias = res?.data ?? res;
      })
    );
  }

  // 🔹 TODOS LOS FOROS
  getForos(): Observable<any> {
    if (this.cacheForos) {
      return of(this.cacheForos);
    }

    return this.http.get(`${this.apiUrl}/foros`).pipe(
      tap((res: any) => {
        this.cacheForos = res?.data ?? res;
      })
    );
  }

  // 🔹 UN FORO
  getForo(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${id}`);
  }

  // 🔹 MIS FOROS (🔐 interceptor se encarga)
  getMisForos(): Observable<any> {
    return this.http.get(`${this.apiUrl}/mis-foros`);
  }

  // 🔹 FOROS PÚBLICOS
  getForosPublicos(): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros-publicos`);
  }

  // 🔹 ACTUALIZAR
  actualizarForo(id: number, data: any): Observable<any> {
    return this.http.put(`${this.apiUrl}/foros/${id}`, data).pipe(
      tap(() => this.cacheForos = null)
    );
  }

  // 🔹 ELIMINAR
  deleteForo(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/foros/${id}`).pipe(
      tap(() => this.cacheForos = null)
    );
  }
}