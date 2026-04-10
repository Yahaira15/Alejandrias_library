import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ForoService } from '../../services/foro';
import { Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

@Component({
  selector: 'app-lista-foros',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './lista-foros.html',
  styleUrls: ['./lista-foros.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ListaForos implements OnInit {
  foros: any[] = [];
  cargando = false;
  usuario: any;
  rol = '';

  apodoUsuario = '';

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.rol = this.usuario.usuario_rol;
    this.apodoUsuario = this.usuario.apodo || this.usuario.apodoUsuario || this.usuario.nombre || '';
    this.cargarForos();
  }

  cargarForos(): void {
    this.cargando = true;

    if (this.rol === 'lider') {
      this.foroService.getMisForos().subscribe({
        next: (res: any) => this.enriquecerForos(res?.data ?? res ?? []),
        error: (err) => {
          console.error(err);
          this.cargando = false;
          this.cdr.detectChanges();
        }
      });
    } else {
      this.foroService.getForosPublicos().subscribe({
        next: (res: any) => this.enriquecerForos(res ?? []),
        error: (err) => {
          console.error('ERROR FOROS PUBLICOS:', err);
          this.cargando = false;
          this.cdr.detectChanges();
        }
      });
    }
  }

  enriquecerForos(foros: any[]): void {
    if (!foros.length) {
      this.foros = [];
      this.cargando = false;
      this.cdr.detectChanges();
      return;
    }

    const solicitudes = foros.map(foro => this.foroService.getPublicaciones(foro.foro_id));

    forkJoin(solicitudes).subscribe({
      next: (resultados) => {
        this.foros = foros.map((foro, index) => {
          const publicaciones = Array.isArray(resultados[index]) ? resultados[index] : [];
          const comentariosCount = publicaciones.reduce(
            (total: number, publicacion: any) => total + (publicacion.comentarios_count || 0),
            0
          );

          return {
            ...foro,
            publicaciones_count: publicaciones.length,
            comentarios_count_total: comentariosCount
          };
        });
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error contando comentarios por foro:', err);
        this.foros = foros;
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  irACrearForo(): void {
    this.router.navigate(['/foros/crear']);
  }

  irAChatIa(): void {
    this.router.navigate(['/chat-ia']);
  }

  editarForo(id: number): void {
    this.router.navigate(['/foros/editar', id]);
  }

  eliminarForo(id: number): void {
    const confirmar = confirm('�Estas seguro de que quieres eliminar este foro?');
    if (!confirmar) return;

    this.foroService.deleteForo(id).subscribe({
      next: () => {
        this.foros = this.foros.filter(f => f.foro_id !== id);
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando foro', err);
      }
    });
  }

  trackByForoId(index: number, foro: any): number {
    return foro.foro_id;
  }

  logout(): void {
    localStorage.removeItem('usuario');
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }
}
