import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
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
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class CrearForo implements OnInit {
  foroForm: FormGroup;
  categorias: any[] = [];
  id = 0;
  passwordVisible = false;
  selectedImage: File | null = null;
  imagePreview: string | null = null;
  guardando = false;
  feedbackMensaje = '';
  feedbackTipo: 'success' | 'error' | '' = '';
  private feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private fb: FormBuilder,
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.foroForm = this.fb.group({
      foro_titulo: ['', Validators.required],
      foro_descripcion: ['', Validators.required],
      foro_categoria_id: ['', Validators.required],
      foro_privado: [true],
      foro_password: ['', [
        Validators.required,
        Validators.pattern(/^[A-Za-z0-9]{8}$/)
      ]]
    });
  }

  ngOnInit(): void {
    this.cargarCategorias();

    const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.id = usuario.usuario_id;
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

  crearForo() {
    if (this.guardando) {
      return;
    }

    if (this.foroForm.invalid) {
      this.foroForm.markAllAsTouched();
      this.mostrarFeedback('error', 'Por favor completa todos los campos.');
      return;
    }

    this.guardando = true;
    this.cdr.markForCheck();

    const data = this.crearPayloadForo();

    this.foroService.crearForo(data).subscribe({
      next: () => {
        this.mostrarFeedback('success', 'Foro creado correctamente.');
        setTimeout(() => this.router.navigate(['/foros']), 700);
      },
      error: (err) => {
        console.error('Error al crear foro:', err);
        this.mostrarFeedback('error', this.mensajeModeracionDesdeError(err, 'Error al crear el foro.'));
        this.guardando = false;
        this.cdr.markForCheck();
      }
    });
  }

  mostrarFeedback(tipo: 'success' | 'error', mensaje: string): void {
    this.feedbackTipo = tipo;
    this.feedbackMensaje = mensaje;

    if (this.feedbackTimeout) {
      clearTimeout(this.feedbackTimeout);
    }

    this.feedbackTimeout = setTimeout(() => {
      this.feedbackMensaje = '';
      this.feedbackTipo = '';
      this.cdr.detectChanges();
    }, 6500);

    this.cdr.markForCheck();
  }

  cerrarFeedback(): void {
    this.feedbackMensaje = '';
    this.feedbackTipo = '';

    if (this.feedbackTimeout) {
      clearTimeout(this.feedbackTimeout);
      this.feedbackTimeout = null;
    }

    this.cdr.markForCheck();
  }

  private mensajeModeracionDesdeError(err: any, fallback: string): string {
    return err?.error?._moderacion?.mensaje_usuario
      || err?.error?.error
      || fallback;
  }

  setPrivado(privado: boolean) {
    this.foroForm.patchValue({ foro_privado: privado });
    this.actualizarValidadoresPassword(privado);
    this.cdr.markForCheck();
  }

  private actualizarValidadoresPassword(privado: boolean) {
    const passwordControl = this.foroForm.get('foro_password');

    if (privado) {
      passwordControl?.setValidators([
        Validators.required,
        Validators.pattern(/^[A-Za-z0-9]{8}$/)
      ]);
      passwordControl?.setValue('');
    } else {
      passwordControl?.clearValidators();
      passwordControl?.setValue(null);
      this.passwordVisible = false;
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
      this.mostrarFeedback('error', 'Solo se permiten imagenes JPG y PNG.');
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

  regresar() {
    this.router.navigate(['/foros']);
  }

  private crearPayloadForo(): FormData {
    const formData = new FormData();
    const privado = !!this.foroForm.value.foro_privado;

    formData.append('foro_titulo', this.foroForm.value.foro_titulo);
    formData.append('foro_descripcion', this.foroForm.value.foro_descripcion);
    formData.append('foro_categoria_id', String(Number(this.foroForm.value.foro_categoria_id)));
    formData.append('foro_creador_id', String(this.id));
    formData.append('foro_privado', privado ? '1' : '0');

    if (privado && this.foroForm.value.foro_password) {
      formData.append('foro_password', this.foroForm.value.foro_password);
    }

    if (this.selectedImage) {
      formData.append('foro_imagen', this.selectedImage);
    }

    return formData;
  }
}
