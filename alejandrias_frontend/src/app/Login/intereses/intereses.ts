import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { API_URL } from '../../api.config';

interface Interes {
  id: string;
  nombre: string;
  icono: string;
}

interface UsuarioPerfil {
  usuario_intereses?: string[] | string | null;
}

@Component({
  selector: 'app-intereses',
  imports: [CommonModule, FormsModule],
  templateUrl: './intereses.html',
  styleUrl: './intereses.scss'
})
export class Intereses implements OnInit {
  constructor(
    private http: HttpClient,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  readonly intereses: Interes[] = [
    { id: 'programacion', nombre: 'Programacion', icono: 'intereses/laptop.png' },
    { id: 'matematicas', nombre: 'Matematicas', icono: 'intereses/calculadora.png' },
    { id: 'historia', nombre: 'Historia', icono: 'intereses/globo.png' },
    { id: 'literatura', nombre: 'Literatura', icono: 'intereses/tintero.png' },
    { id: 'biologia', nombre: 'Biologia', icono: 'intereses/microscopio.png' },
    { id: 'politica', nombre: 'Politica', icono: 'intereses/balanza.png' },
    { id: 'idiomas', nombre: 'Idiomas', icono: 'intereses/idiomas.png' },
    { id: 'bienestar', nombre: 'Bienestar', icono: 'intereses/bienestar.png' },
  ];

  seleccionados: string[] = [];
  cargando = false;
  errorMensaje = '';

  ngOnInit(): void {
    const usuarioGuardado = localStorage.getItem('usuario');
    if (!usuarioGuardado) return;

    const usuario = JSON.parse(usuarioGuardado) as UsuarioPerfil;
    this.seleccionados = this.normalizarIntereses(usuario.usuario_intereses);
  }

  toggleInteres(interesId: string): void {
    if (this.cargando) return;

    if (this.seleccionados.includes(interesId)) {
      this.seleccionados = this.seleccionados.filter(id => id !== interesId);
      return;
    }

    this.seleccionados = [...this.seleccionados, interesId];
  }

  interesSeleccionado(interesId: string): boolean {
    return this.seleccionados.includes(interesId);
  }

  finalizar(): void {
    this.errorMensaje = '';

    if (this.cargando) return;

    if (this.seleccionados.length === 0) {
      this.errorMensaje = 'Selecciona al menos un interes';
      return;
    }

    this.cargando = true;

    this.http.put<{ usuario: UsuarioPerfil }>(`${API_URL}/perfil/intereses`, {
      usuario_intereses: this.seleccionados,
    }).subscribe({
      next: (res) => {
        const usuarioActual = JSON.parse(localStorage.getItem('usuario') || '{}') as UsuarioPerfil;
        localStorage.setItem('usuario', JSON.stringify({ ...usuarioActual, ...res.usuario }));
        this.cargando = false;
        this.router.navigate(['/home']);
      },
      error: (err) => {
        this.cargando = false;
        this.errorMensaje = err.error?.detalle || err.error?.error || 'No se pudieron guardar los intereses';
        this.cdr.detectChanges();
      }
    });
  }

  private normalizarIntereses(intereses: string[] | string | null | undefined): string[] {
    if (Array.isArray(intereses)) return intereses;

    if (typeof intereses === 'string' && intereses.trim()) {
      try {
        const parsed = JSON.parse(intereses) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
      } catch {
        return [];
      }
    }

    return [];
  }
}
