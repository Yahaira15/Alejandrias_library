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

  crearForo(data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros`, data).pipe(
      tap(() => this.cacheForos = null)
    );
  }

  registrarEnForo(id: number, foroPassword: string | null = null): Observable<any> {
    const body = foroPassword ? { foro_password: foroPassword } : {};
    return this.http.post(`${this.apiUrl}/foros/${id}/registrarse`, body);
  }

  verificarRegistroForo(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${id}/registro`);
  }

  dejarDeSeguirForo(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/foros/${id}/registro`);
  }

  buscarForoPrivado(foroPassword: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros/privado/buscar`, {
      foro_password: foroPassword
    });
  }

  revelarPasswordForo(id: number, usuarioPassword: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros/${id}/password`, {
      usuario_password: usuarioPassword
    });
  }

  getCategorias(): Observable<any> {
    if (this.cacheCategorias) return of(this.cacheCategorias);
    return this.http.get(`${this.apiUrl}/categorias`).pipe(
      tap((res: any) => this.cacheCategorias = res?.data ?? res)
    );
  }

  getForos(): Observable<any> {
    if (this.cacheForos) return of(this.cacheForos);
    return this.http.get(`${this.apiUrl}/foros`).pipe(
      tap((res: any) => this.cacheForos = res?.data ?? res)
    );
  }

  getForo(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${id}`);
  }

  getPublicacion(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/publicaciones/${id}`);
  }

  registrarLecturaPublicacion(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/logros/eventos`, {
      accion: 'lectura_publicacion',
      publicacion_id: id,
      metadata: {
        origen: 'ver_publicacion'
      }
    });
  }

  verificarRegistroPublicacion(id: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/publicaciones/${id}/registro`);
  }

  getPublicaciones(foroId: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${foroId}/publicaciones`);
  }

  crearPublicacion(foroId: number, data: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros/${foroId}/publicaciones`, data);
  }

  actualizarPublicacion(id: number, data: any): Observable<any> {
    return this.http.put(`${this.apiUrl}/publicaciones/${id}`, data);
  }

  eliminarPublicacion(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/publicaciones/${id}`);
  }

  getComentariosPublicacion(publicacionId: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/publicaciones/${publicacionId}/comentarios`);
  }

  crearComentarioPublicacion(publicacionId: number, contenido: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/publicaciones/${publicacionId}/comentarios`, {
      comentario_contenido: contenido
    });
  }

  getRespuestasComentario(comentarioId: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/comentarios/${comentarioId}/respuestas`);
  }

  responderComentario(comentarioId: number, contenido: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/comentarios/${comentarioId}/respuestas`, {
      comentario_contenido: contenido
    });
  }

  getComentarios(foroId: number): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros/${foroId}/comentarios`);
  }

  getMisForos(): Observable<any> {
    return this.http.get(`${this.apiUrl}/mis-foros`);
  }

  getForosPublicos(): Observable<any> {
    return this.http.get(`${this.apiUrl}/foros-publicos`);
  }

  actualizarForo(id: number, data: any): Observable<any> {
    const request = data instanceof FormData
      ? this.http.post(`${this.apiUrl}/foros/${id}`, data)
      : this.http.put(`${this.apiUrl}/foros/${id}`, data);

    return request.pipe(
      tap(() => this.cacheForos = null)
    );
  }

  deleteForo(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/foros/${id}`).pipe(
      tap(() => this.cacheForos = null)
    );
  }

  crearComentario(foroId: number, contenido: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/foros/${foroId}/comentarios`, {
      comentario_contenido: contenido
    });
  }

  actualizarComentario(id: number, contenido: string): Observable<any> {
    return this.http.put(`${this.apiUrl}/comentarios/${id}`, {
      comentario_contenido: contenido
    });
  }

  eliminarComentario(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/comentarios/${id}`);
  }
}
