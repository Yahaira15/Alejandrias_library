import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit, ChangeDetectorRef } from '@angular/core'; // Añadimos ChangeDetectorRef
import { ReactiveFormsModule, FormBuilder, FormGroup, FormsModule, Validators } from '@angular/forms';
import { ForoService } from '../../services/foro';
import { ActivatedRoute, Router } from '@angular/router';

@Component({
  selector: 'app-editar-foro',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule
  ],
  templateUrl: './editar-foro.html',
  styleUrls: ['./editar-foro.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class EditarForo implements OnInit {

  foroForm: FormGroup;
  categorias: any[] = [];
  foroId!: number;

  constructor(
    private fb: FormBuilder,
    private foroService: ForoService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef 
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

    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.foroId = Number(id);
      this.cargarForo(this.foroId);
    }
  }

  get esPublico(): boolean {
    return !this.foroForm.get('foro_privado')?.value;
  }

  cargarCategorias() {
    this.foroService.getCategorias().subscribe({
      next: (res: any) => {
        this.categorias = res;
        this.cdr.markForCheck(); 
      },
      error: (err) => {
        console.error('Error cargando categorías', err);
      }
    });
  }

  cargarForo(id: number) {
    this.foroService.getForo(id).subscribe({
      next: (res: any) => {
        this.foroForm.patchValue({
          foro_titulo: res.foro_titulo,
          foro_descripcion: res.foro_descripcion,
          foro_categoria_id: res.foro_categoria_id,
          foro_privado: res.foro_privado
        });
        this.cdr.markForCheck(); 
      },
      error: (err) => {
        console.error("Error cargando foro", err);
      }
    });
  }

  actualizarForo() {
    if (this.foroForm.invalid) {
      alert("⚠️ Completa todos los campos");
      return;
    }

    const data = {
      ...this.foroForm.value,
      foro_categoria_id: Number(this.foroForm.value.foro_categoria_id)
    };

    this.foroService.actualizarForo(this.foroId, data).subscribe({
      next: () => {
        alert("Foro actualizado correctamente");
        this.router.navigate(['/foros']);
      },
      error: (err) => {
        console.error("Error", err);
        alert("Error al actualizar");
      }
    });
  }


  setEstado(privado: boolean) {
    this.foroForm.patchValue({ foro_privado: privado });
    this.cdr.markForCheck(); 
  }

  volver() {
    this.router.navigate(['/foros']);
  }
}