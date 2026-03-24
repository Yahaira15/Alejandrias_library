import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ForoService } from '../../services/foro';

@Component({
  selector: 'app-lista-foros',
  imports: [ CommonModule],
  templateUrl: './lista-foros.html',
  styleUrl: './lista-foros.scss',
})
export class ListaForos implements OnInit{
    foros: any[] = [];

  constructor(private foroService: ForoService) {}

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
}
