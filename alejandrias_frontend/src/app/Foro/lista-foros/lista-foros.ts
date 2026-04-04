import {
  ChangeDetectionStrategy,
  Component,
  OnInit,
  ChangeDetectorRef
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ForoService } from '../../services/foro';
import { Router } from '@angular/router';

@Component({
  selector: 'app-lista-foros',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './lista-foros.html',
  styleUrls: ['./lista-foros.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ListaForos implements OnInit {

  foros: any[] = [];
  cargando: boolean = false;
  usuario: any;
  rol: string = '';

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.rol = this.usuario.usuario_rol;

    this.cargarForos();
  }

  cargarForos(): void {
  this.cargando = true;

  if (this.rol === 'lider') {

    this.foroService.getMisForos().subscribe({
      next: (res: any) => {
        this.foros = res?.data ?? res ?? [];
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error(err);
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });

  } else {
      this.foroService.getForosPublicos().subscribe({
      next: (res: any) => {
        console.log('FOROS PUBLICOS:', res);
        this.foros = res ?? [];
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('ERROR FOROS PUBLICOS:', err);
      }
    });
  }
}

  irACrearForo(): void {
    this.router.navigate(['/foros/crear']);
  }

  editarForo(id: number): void {
    this.router.navigate(['/foros/editar', id]);
  }

  eliminarForo(id: number): void {
    const confirmar = confirm('¿Estás seguro de que quieres eliminar este foro?');
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

  logout() {
    localStorage.removeItem('usuario');
    localStorage.removeItem('token'); 
    this.router.navigate(['/login']);
  }
}
