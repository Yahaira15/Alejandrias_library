import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Injectable({ providedIn: 'root' })
export class PerfilService {

  constructor(private http: HttpClient) {}

  private apiBaseUrl(): string {
    const localBase = 'http://127.0.0.1:8000';

    if (typeof window === 'undefined') {
      return localBase;
    }

    const hostname = window.location.hostname;

    if (!hostname || hostname === 'localhost' || hostname === '127.0.0.1') {
      return localBase;
    }

    return `${window.location.protocol}//${hostname}:8000`;
  }

  private get apiUrl(): string {
    return `${this.apiBaseUrl()}/api`;
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
