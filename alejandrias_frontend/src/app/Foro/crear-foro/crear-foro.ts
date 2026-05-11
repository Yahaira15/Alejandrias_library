import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit } from '@angular/core';
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
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class CrearForo implements OnInit {

  foroForm: FormGroup;
  categorias: any[] = [];
  id: number = 0;

  constructor(
    private fb: FormBuilder,
    private foroService: ForoService,
    private router: Router
  ) {
    this.foroForm = this.fb.group({
    foro_titulo: ['', Validators.required],
    foro_descripcion: ['', Validators.required],
    foro_categoria_id: ['', Validators.required],
    foro_privado: [false],
    foro_password: [null]
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

    const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.id = usuario.usuario_id;
  }

    crearForo() {

   
    if (this.foroForm.invalid) {
      this.foroForm.markAllAsTouched();
      alert("Por favor completa todos los campos");
      return;
    }

    const data = {
      ...this.foroForm.value,
      foro_categoria_id: Number(this.foroForm.value.foro_categoria_id),
      foro_creador_id: this.id,
      foro_password: this.foroForm.value.foro_privado ? this.foroForm.value.foro_password : null
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
    const passwordControl = this.foroForm.get('foro_password');

    if (valor) {
      passwordControl?.setValidators([
        Validators.required,
        Validators.pattern(/^[A-Za-z0-9]{8}$/)
      ]);
    } else {
      passwordControl?.clearValidators();
      passwordControl?.setValue(null);
    }

    passwordControl?.updateValueAndValidity();
    this.indicatorStyle(valor);
  }

  regresar() {
    this.router.navigate(['/foros']);
  }

  indicatorStyle(privado: boolean) {
    const indicator = document.getElementById('indicator');
    if (indicator) {
      if (privado) {
        indicator.style.backgroundColor = '#FF6347'; // Rojo para privado
      } else {
        indicator.style.backgroundColor = '#7edd8a'; // Azul para público
      }
    }
  }
}
