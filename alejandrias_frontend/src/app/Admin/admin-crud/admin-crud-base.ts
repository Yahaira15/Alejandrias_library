import { ChangeDetectorRef, Directive, OnInit } from '@angular/core';
import { AdminService } from '../../services/admin.service';

export interface AdminField {
  key: string;
  label: string;
  type?: 'text' | 'number' | 'textarea' | 'select' | 'checkbox' | 'password';
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
export abstract class AdminCrudBase implements OnInit {
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

  protected constructor(
    protected adminService: AdminService,
    protected cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.resetFormulario();
    this.cargar();
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
        this.error = err?.error?.error || 'No se pudieron cargar los datos.';
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
        this.mensaje = this.editandoId ? 'Registro actualizado.' : 'Registro creado.';
        this.guardando = false;
        this.cancelar();
        this.cargar();
      },
      error: (err) => {
        this.error = err?.error?.message || err?.error?.error || 'No se pudo guardar el registro.';
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
      this.formulario[field.key] = field.clearOnEdit
        ? ''
        : registro[field.key] ?? (field.type === 'checkbox' ? false : '');
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
        this.mensaje = 'Registro eliminado.';
        this.cargar();
      },
      error: (err) => {
        this.error = err?.error?.message || err?.error?.error || 'No se pudo eliminar el registro.';
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

    for (const field of this.config.fields) {
      this.formulario[field.key] = field.type === 'checkbox' ? false : '';
    }
  }

  cambiarBusqueda(): void {
    this.paginaActual = 1;
  }

  get columnas(): AdminField[] {
    return this.config.fields.filter((field) => !field.hideInTable && field.type !== 'password');
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

  protected prepararPayload(payload: any): any {
    return payload;
  }
}
