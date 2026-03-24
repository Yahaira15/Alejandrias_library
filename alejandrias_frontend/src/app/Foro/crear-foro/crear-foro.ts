import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, FormsModule, Validators } from '@angular/forms';
import { ForoService } from '../../services/foro';
import { Router } from '@angular/router';

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
    private foroService: ForoService,
    private router: Router
  ) {
    this.foroForm = this.fb.group({
    foro_titulo: ['', Validators.required],
    foro_descripcion: ['', Validators.required],
    foro_categoria_id: ['', Validators.required],
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

   
    if (this.foroForm.invalid) {
      alert("Por favor completa todos los campos");
      return;
    }

    const data = {
      ...this.foroForm.value,
      foro_categoria_id: Number(this.foroForm.value.foro_categoria_id)
    };

    this.foroService.crearForo(data).subscribe({
      next: (res) => {
        console.log('Foro creado', res);

        alert("Foro creado correctamente");

        this.router.navigate(['/foros']);
      },
      error: (err) => {
        console.error('Error completo:', err.error);

        alert("Error al crear el foro");
      }
    });
  }

  setPrivado(valor: boolean) {
    this.foroForm.patchValue({ foro_privado: valor });
  }

  regresar() {
    this.router.navigate(['/foros']);
  }
}
