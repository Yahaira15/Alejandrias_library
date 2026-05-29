import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ForoService } from '../../services/foro';

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
  passwordVisible = false;
  selectedImage: File | null = null;
  imagePreview: string | null = null;
  guardando = false;
  private teniaPasswordPrivado = false;

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
      foro_privado: [false],
      foro_password: [null]
    });
  }

  ngOnInit(): void {
    this.cargarCategorias();

    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.foroId = Number(id);
      const foroNavegado = history.state?.foro;
      if (foroNavegado?.foro_id === this.foroId) {
        this.aplicarForoEnFormulario(foroNavegado);
      }
      this.cargarForo(this.foroId);
    }
  }

  cargarCategorias() {
    this.foroService.getCategorias().subscribe({
      next: (res: any) => {
        this.categorias = res?.data ?? res;
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
        const foro = res?.data ?? res;
        this.aplicarForoEnFormulario(foro);
      },
      error: (err) => {
        console.error('Error cargando foro', err);
        alert('No se pudo cargar el foro');
      }
    });
  }

  private aplicarForoEnFormulario(foro: any) {
    const privado = !!foro.foro_privado;
    this.teniaPasswordPrivado = privado;

    this.foroForm.patchValue({
      foro_titulo: foro.foro_titulo,
      foro_descripcion: foro.foro_descripcion,
      foro_categoria_id: foro.foro_categoria_id,
      foro_privado: privado,
      foro_password: null
    }, { emitEvent: false });

    const imagen = foro.foro_imagen_url || foro.foro_imagen;
    if (imagen) {
      this.imagePreview = this.normalizarImagenForo(imagen);
    }

    this.actualizarValidadoresPassword(privado);
    this.cdr.markForCheck();
  }

  actualizarForo() {
    if (this.guardando) {
      return;
    }

    if (this.foroForm.invalid) {
      this.foroForm.markAllAsTouched();
      alert('Completa todos los campos');
      return;
    }

    this.guardando = true;
    this.cdr.markForCheck();

    const data = this.crearPayloadForo();

    this.foroService.actualizarForo(this.foroId, data).subscribe({
      next: () => {
        alert('Foro actualizado correctamente');
        this.router.navigate(['/foros']);
      },
      error: (err) => {
        console.error('Error al actualizar foro:', err);
        alert('Error al actualizar el foro');
        this.guardando = false;
        this.cdr.markForCheck();
      }
    });
  }

  setEstado(privado: boolean) {
    this.foroForm.patchValue({ foro_privado: privado });
    this.actualizarValidadoresPassword(privado, true);
    if (!privado) {
      this.passwordVisible = false;
    }
    this.cdr.markForCheck();
  }

  private actualizarValidadoresPassword(privado: boolean, cambioManual = false) {
    const passwordControl = this.foroForm.get('foro_password');

    if (privado) {
      const validators = [Validators.pattern(/^[A-Za-z0-9]{8}$/)];
      if (!this.teniaPasswordPrivado || cambioManual) {
        validators.unshift(Validators.required);
      }
      passwordControl?.setValidators(validators);
      if (cambioManual) {
        passwordControl?.setValue('');
      }
    } else {
      passwordControl?.clearValidators();
      passwordControl?.setValue(null);
    }

    passwordControl?.updateValueAndValidity();
  }

  togglePasswordVisibility() {
    this.passwordVisible = !this.passwordVisible;
    this.cdr.markForCheck();
  }

  onImageSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) {
      return;
    }

    const file = input.files[0];
    if (!['image/png', 'image/jpeg'].includes(file.type)) {
      alert('Solo se permiten imágenes JPG y PNG.');
      input.value = '';
      return;
    }

    this.selectedImage = file;
    if (this.imagePreview?.startsWith('blob:')) {
      URL.revokeObjectURL(this.imagePreview);
    }
    this.imagePreview = URL.createObjectURL(file);
    this.cdr.markForCheck();
  }

  clearImage(event: Event, input: HTMLInputElement) {
    event.stopPropagation();
    if (this.imagePreview?.startsWith('blob:')) {
      URL.revokeObjectURL(this.imagePreview);
    }
    this.selectedImage = null;
    this.imagePreview = null;
    input.value = '';
    this.cdr.markForCheck();
  }

  volver() {
    this.router.navigate(['/foros']);
  }

  regresar() {
    this.volver();
  }

  private crearPayloadForo(): FormData {
    const formData = new FormData();
    const privado = !!this.foroForm.value.foro_privado;

    formData.append('_method', 'PUT');
    formData.append('foro_titulo', this.foroForm.value.foro_titulo);
    formData.append('foro_descripcion', this.foroForm.value.foro_descripcion);
    formData.append('foro_categoria_id', String(Number(this.foroForm.value.foro_categoria_id)));
    formData.append('foro_privado', privado ? '1' : '0');

    if (privado && this.foroForm.value.foro_password) {
      formData.append('foro_password', this.foroForm.value.foro_password);
    }

    if (this.selectedImage) {
      formData.append('foro_imagen', this.selectedImage);
    }

    return formData;
  }

  private normalizarImagenForo(imagen: string): string {
    if (!imagen) return '';

    if (imagen.startsWith('http://localhost/storage')) {
      return imagen.replace('http://localhost', 'http://127.0.0.1:8000');
    }

    if (/^(https?:|data:|blob:)/i.test(imagen)) {
      return imagen;
    }

    const ruta = imagen.startsWith('/') ? imagen : `/${imagen}`;
    return `http://127.0.0.1:8000${ruta.startsWith('/storage') ? ruta : `/storage${ruta}`}`;
  }
}
