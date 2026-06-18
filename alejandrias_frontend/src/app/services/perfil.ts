import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { API_URL } from '../api.config';

@Injectable({ providedIn: 'root' })
export class PerfilService {

  constructor(private http: HttpClient) {}

  private get apiUrl(): string {
    return API_URL;
  }

  getPerfil() {
    return this.http.get(`${this.apiUrl}/perfil`);
  }

  getLogros() {
    return this.http.get(`${this.apiUrl}/logros`);
  }

  sincronizarLogros() {
    return this.http.post(`${this.apiUrl}/logros/sincronizar`, {});
  }

  cargarLogrosDemo() {
    return this.http.post(`${this.apiUrl}/logros/demo`, {});
  }

  updatePerfil(data: any) {
    return this.http.put(`${this.apiUrl}/perfil`, data);
  }

  deleteCuenta() {
    return this.http.delete(`${this.apiUrl}/perfil`);
  }
}
