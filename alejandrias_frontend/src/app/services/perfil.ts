import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Injectable({ providedIn: 'root' })
export class PerfilService {

  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getPerfil() {
    return this.http.get(`${this.apiUrl}/perfil`);
  }

  updatePerfil(data: any) {
    return this.http.put(`${this.apiUrl}/perfil`, data);
  }

  deleteCuenta() {
    return this.http.delete(`${this.apiUrl}/perfil`);
  }
}