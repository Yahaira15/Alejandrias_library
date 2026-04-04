import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of, tap } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ForoService {

  private apiUrl = 'http://127.0.0.1:8000/api';

  // 🔥 Cache en memoria
  private cacheForos: any[] | null = null;
  private cacheCategorias: any[] | null = null;

  constructor(private http: HttpClient) {}

  crearForo(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros`, data).pipe(
      tap(() => this.cacheForos = null) 
    );
  }

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

  getForo(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${id}`);
  }

  getMisForos(): Observable<any> {

  const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
  const id = usuario.usuario_id;
  console.log('USUARIO:', usuario);

  return this.http.get(`${this.apiUrl}/mis-foros/${id}`);
}

  getForosPublicos(): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros-publicos`);
  }

  actualizarForo(id: number, data: any): Observable<any> {
  const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');

  const payload = {
    ...data,
    usuario_id: usuario.usuario_id
  };

  return this.http.put(`${this.apiUrl}/foros/${id}`, payload).pipe(
    tap(() => this.cacheForos = null)
  );
}

  deleteForo(id: number) {
  const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');

  return this.http.delete(`${this.apiUrl}/foros/${id}`, {
    body: {
      usuario_id: usuario.usuario_id
    }
  });
}
} 