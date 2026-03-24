import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ForoService {

  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  crearForo(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros`, data);
  }

  getCategorias(): Observable<any> {
    return this.http.get(`${this.apiUrl}/categorias`);
  }


  getForos() {
  return this.http.get(`${this.apiUrl}/foros`);
  }

getForo(id: number) {
  return this.http.get(`http://127.0.0.1:8000/api/foros/${id}`);
}


actualizarForo(id: number, data: any) {
  return this.http.put(`http://127.0.0.1:8000/api/foros/${id}`, data);
}

deleteForo(id: number): Observable<any> {
  return this.http.delete(`${this.apiUrl}/foros/${id}`);
}
}
