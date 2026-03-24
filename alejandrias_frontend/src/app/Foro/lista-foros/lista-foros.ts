import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ForoService } from '../../services/foro';
import { Router } from '@angular/router';

@Component({
  selector: 'app-lista-foros',
  standalone: true,
  imports: [ CommonModule],
  templateUrl: './lista-foros.html',
  styleUrls: ['./lista-foros.scss'],
})
export class ListaForos implements OnInit{
    foros: any[] = [];

  constructor(private foroService: ForoService, private router: Router) {}

  ngOnInit(): void {
    this.cargarForos();
  }

  cargarForos() {
    this.foroService.getForos().subscribe({
      next: (res: any) => {
        this.foros = res;
        console.log('Foros:', res);
      },
      error: (err) => {
        console.error('Error cargando foros', err);
      }
    });
  }

   irACrearForo() {
    this.router.navigate(['/crear_foro']); 
  }

  editarForo(id: number) {
  this.router.navigate(['/foros/editar', id]);
  }

  eliminarForo(id: number) {
    if (confirm('¿Estás seguro de que quieres eliminar este foro?')) {
      this.foroService.deleteForo(id).subscribe({
        next: () => {
          this.cargarForos();
        },
        error: (err) => {
          console.error('Error eliminando foro', err);
        }
      });
    }
  }
}
