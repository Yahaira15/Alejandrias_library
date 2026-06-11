import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { ListaForos } from '../../Foro/lista-foros/lista-foros';

@Component({
  selector: 'app-home',
  imports: [CommonModule, RouterModule, ListaForos],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home {
  categorias = [
    {
      nombre: 'Ciencias',
      descripcion: 'Explora temas para entender el mundo natural, sus procesos y descubrimientos.',
      subcategorias: ['Biologia', 'Fisica', 'Quimica']
    },
    {
      nombre: 'Tecnologia',
      descripcion: 'Organiza conversaciones sobre herramientas digitales, programacion e innovacion.',
      subcategorias: ['Programacion', 'Inteligencia Artificial', 'Ciberseguridad']
    },
    {
      nombre: 'Humanidades',
      descripcion: 'Reune ideas sobre cultura, pensamiento, historia y formas de expresion.',
      subcategorias: ['Historia', 'Filosofia', 'Literatura']
    },
    {
      nombre: 'Matematicas',
      descripcion: 'Agrupa dudas, retos y explicaciones sobre razonamiento numerico y logico.',
      subcategorias: ['Algebra', 'Geometria', 'Estadistica']
    }
  ];

  constructor(private router: Router) {}

  get usuarioAutenticado(): boolean {
    return typeof localStorage !== 'undefined' && !!localStorage.getItem('token');
  }

  irARegistro(rol: string) {
  this.router.navigate(['/register'], { queryParams: { rol } });
  }

  irALogin() {
    this.router.navigate(['/login'])
  }
}
