import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, FormsModule } from '@angular/forms';
import { ForoService } from '../../services/foro';

@Component({
  selector: 'app-crear-foro',
  standalone: true, 
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule 
  ],
  templateUrl: './crear-foro.html',
  styleUrls: ['./crear-foro.scss'], 
})
export class CrearForo implements OnInit {

  foroForm: FormGroup;
  categorias: any[] = [];

  constructor(
    private fb: FormBuilder,
    private foroService: ForoService
  ) {
    this.foroForm = this.fb.group({
      foro_titulo: [''],
      foro_descripcion: [''],
      foro_categoria_id: [''],
      foro_privado: [false]
    });
  }

  ngOnInit(): void {
    this.cargarCategorias();
  }

  cargarCategorias() {
    this.foroService.getCategorias().subscribe({
      next: (res: any) => {
        this.categorias = res;
      },
      error: (err) => {
        console.error('Error cargando categorías', err);
      }
    });
  }

  crearForo() {

    const usuario_id = 1; 

    const data = {
      ...this.foroForm.value,
      foro_categoria_id: Number(this.foroForm.value.foro_categoria_id),
      foro_creador_id: usuario_id
    };

    this.foroService.crearForo(data).subscribe({
      next: (res) => {
        console.log('Foro creado', res);
      },
      error: (err) => {
        console.error('Error', err);
      }
    });
  }

  setPrivado(valor: boolean) {
    this.foroForm.patchValue({ foro_privado: valor });
  }
}
