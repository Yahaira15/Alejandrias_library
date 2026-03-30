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

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.cargarForos();
  }

  cargarForos(): void {
    this.cargando = true;
    this.foroService.getMisForos().subscribe({
      next: (res: any) => {
        console.log('Mis Foros:', res);

        this.foros = res?.data ?? res ?? [];

        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando foros', err);

        this.foros = [];
        this.cargando = false;

        this.cdr.detectChanges();
      }
    });
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
