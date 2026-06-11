import { ChangeDetectorRef, Directive, OnDestroy, OnInit } from '@angular/core';
import { AdminService } from '../../services/admin.service';

export interface AdminField {
  key: string;
  label: string;
  type?: 'text' | 'number' | 'textarea' | 'select' | 'checkbox' | 'password' | 'image';
  required?: boolean;
  options?: { label: string; value: any }[];
  hideInTable?: boolean;
  clearOnEdit?: boolean;
  placeholder?: string;
  helpText?: string;
}

export interface AdminCrudConfig {
  titulo: string;
  recurso: string;
  idKey: string;
  searchKeys: string[];
  fields: AdminField[];
}

@Directive()
export abstract class AdminCrudBase implements OnInit, OnDestroy {
  private readonly imagenMaxBytes = 5 * 1024 * 1024;
  abstract config: AdminCrudConfig;
  registros: any[] = [];
  formulario: any = {};
  editandoId: number | null = null;
  busqueda = '';
  paginaActual = 1;
  filasPorPagina = 10;
  cargando = false;
  guardando = false;
  mostrandoFormulario = false;
  mensaje = '';
  error = '';
  previewImagenes: Record<string, string | null> = {};
  private avisoTimer: ReturnType<typeof setTimeout> | null = null;

  protected constructor(
    protected adminService: AdminService,
    protected cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.resetFormulario();
    this.cargar();
  }

  ngOnDestroy(): void {
    this.limpiarAvisoTimer();
  }

  cargar(): void {
    this.cargando = true;
    this.error = '';

    this.adminService.listar(this.config.recurso).subscribe({
      next: (res) => {
        this.registros = Array.isArray(res) ? res : [];
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.mostrarError(err?.error?.error || 'No se pudieron cargar los datos.');
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  guardar(): void {
    if (this.guardando) return;

    this.guardando = true;
    const payload = this.prepararPayload({ ...this.formulario });
    const request = this.editandoId
      ? this.adminService.actualizar(this.config.recurso, this.editandoId, payload)
      : this.adminService.crear(this.config.recurso, payload);

    request.subscribe({
      next: () => {
        this.mostrarMensaje(this.editandoId ? 'Registro actualizado.' : 'Registro creado.');
        this.guardando = false;
        this.cancelar();
        this.cargar();
      },
      error: (err) => {
        this.mostrarError(err?.error?.message || err?.error?.error || 'No se pudo guardar el registro.');
        this.guardando = false;
        this.cdr.detectChanges();
      }
    });
  }

  editar(registro: any): void {
    this.editandoId = Number(registro[this.config.idKey]);
    this.mostrandoFormulario = true;
    this.formulario = {};

    for (const field of this.config.fields) {
      this.formulario[field.key] = field.type === 'image'
        ? null
        : field.clearOnEdit
        ? ''
        : registro[field.key] ?? (field.type === 'checkbox' ? false : '');

      if (field.type === 'image') {
        this.previewImagenes[field.key] = registro[field.key] ?? null;
      }
    }

    this.cdr.detectChanges();
  }

  nuevo(): void {
    this.editandoId = null;
    this.resetFormulario();
    this.mostrandoFormulario = true;
    this.cdr.detectChanges();
  }

  eliminar(registro: any): void {
    const id = Number(registro[this.config.idKey]);
    if (!confirm('¿Seguro que quieres eliminar este registro?')) return;

    this.adminService.eliminar(this.config.recurso, id).subscribe({
      next: () => {
        this.mostrarMensaje('Registro eliminado.');
        this.cargar();
      },
      error: (err) => {
        this.mostrarError(err?.error?.message || err?.error?.error || 'No se pudo eliminar el registro.');
        this.cdr.detectChanges();
      }
    });
  }

  cancelar(): void {
    this.editandoId = null;
    this.mostrandoFormulario = false;
    this.resetFormulario();
    this.cdr.detectChanges();
  }

  resetFormulario(): void {
    this.formulario = {};
    this.previewImagenes = {};

    for (const field of this.config.fields) {
      this.formulario[field.key] = field.type === 'checkbox' ? false : '';
      if (field.type === 'image') {
        this.previewImagenes[field.key] = null;
      }
    }
  }

  cambiarBusqueda(): void {
    this.paginaActual = 1;
  }

  get columnas(): AdminField[] {
    return this.config.fields.filter((field) => !field.hideInTable && field.type !== 'password' && field.type !== 'textarea');
  }

  get filtrados(): any[] {
    const termino = this.busqueda.toLowerCase().trim();
    if (!termino) return this.registros;

    return this.registros.filter((registro) =>
      this.config.searchKeys.some((key) => String(registro[key] ?? '').toLowerCase().includes(termino))
    );
  }

  get totalPaginas(): number {
    return Math.max(1, Math.ceil(this.filtrados.length / this.filasPorPagina));
  }

  get pagina(): any[] {
    const inicio = (this.paginaActual - 1) * this.filasPorPagina;
    return this.filtrados.slice(inicio, inicio + this.filasPorPagina);
  }

  cambiarPagina(delta: number): void {
    this.paginaActual = Math.min(this.totalPaginas, Math.max(1, this.paginaActual + delta));
  }

  esRequerido(field: AdminField): boolean {
    return field.required || false;
  }

  valorCelda(registro: any, columna: AdminField): string {
    const valor = registro[columna.key];

    if (columna.type === 'checkbox') {
      return valor ? 'Si' : 'No';
    }

    return valor ?? '';
  }

  imagenUrl(url: string | null | undefined): string {
    return this.adminService.resolverUrlImagen(url);
  }

  seleccionarImagen(event: Event, field: AdminField): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
      return;
    }

    if (file.size > this.imagenMaxBytes) {
      this.mostrarError('La imagen no puede superar los 5 MB.');
      input.value = '';
      this.cdr.detectChanges();
      return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      this.mostrarError('Solo se permiten imagenes JPG, PNG o WEBP.');
      input.value = '';
      this.cdr.detectChanges();
      return;
    }

    this.formulario[field.key] = file;
    const previewAnterior = this.previewImagenes[field.key];

    if (previewAnterior?.startsWith('blob:')) {
      URL.revokeObjectURL(previewAnterior);
    }

    this.previewImagenes[field.key] = URL.createObjectURL(file);
    this.cdr.detectChanges();
  }

  protected prepararPayload(payload: any): any {
    const tieneArchivo = this.config.fields.some((field) => field.type === 'image' && payload[field.key] instanceof File);

    if (tieneArchivo) {
      const formData = new FormData();

      for (const field of this.config.fields) {
        const value = payload[field.key];
        if (field.type === 'image' && value instanceof File) {
          formData.append(field.key, value);
        } else if (field.type !== 'image' && value !== undefined && value !== null) {
          formData.append(field.key, field.type === 'checkbox' ? (value ? '1' : '0') : String(value));
        }
      }

      return formData;
    }

    return payload;
  }

  protected mostrarMensaje(mensaje: string): void {
    this.mensaje = mensaje;
    this.error = '';
    this.programarOcultarAviso();
  }

  protected mostrarError(error: string): void {
    this.error = error;
    this.mensaje = '';
    this.programarOcultarAviso();
  }

  private programarOcultarAviso(): void {
    this.limpiarAvisoTimer();
    this.avisoTimer = setTimeout(() => {
      this.mensaje = '';
      this.error = '';
      this.avisoTimer = null;
      this.cdr.detectChanges();
    }, 3500);
  }

  private limpiarAvisoTimer(): void {
    if (this.avisoTimer) {
      clearTimeout(this.avisoTimer);
      this.avisoTimer = null;
    }
  }
}
