import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';

type CategoriaTab = 'categorias' | 'subcategorias';

@Component({
  selector: 'app-admin-categorias',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-categorias.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss', './admin-categorias.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminCategorias implements OnInit, OnDestroy {
  categorias: any[] = [];
  subcategorias: any[] = [];
  tab: CategoriaTab = 'categorias';
  busqueda = '';
  cargando = false;
  guardando = false;
  mensaje = '';
  error = '';
  editandoCategoriaId: number | null = null;
  editandoSubcategoriaId: number | null = null;

  categoriaForm = {
    categoria_nombre: '',
    categoria_descripcion: ''
  };

  subcategoriaForm = {
    subcategoria_nombre: '',
    categoria_id: '',
    subcategoria_descripcion: ''
  };

  categoriaImagen: File | null = null;
  categoriaPreview: string | null = null;
  private avisoTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private adminService: AdminService, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.cargarCategorias();
  }

  ngOnDestroy(): void {
    this.limpiarAvisoTimer();
  }

  cargarCategorias(): void {
    this.cargando = true;
    this.adminService.listar('categorias').subscribe({
      next: (res: any) => {
        this.categorias = res?.data ?? res ?? [];
        this.cargando = false;
        this.cargarTodasSubcategorias();
      },
      error: (err) => {
        this.mostrarError(err?.error?.message || 'No se pudieron cargar las categorias.');
        this.cargando = false;
        this.cdr.markForCheck();
      }
    });
  }

  cargarTodasSubcategorias(): void {
    const solicitudes = this.categorias.map((categoria) =>
      this.adminService.listarSubcategoriasCategoria(Number(categoria.categoria_id))
    );

    if (!solicitudes.length) {
      this.subcategorias = [];
      this.cdr.markForCheck();
      return;
    }

    let pendientes = solicitudes.length;
    const resultado: any[] = [];

    solicitudes.forEach((solicitud) => {
      solicitud.subscribe({
        next: (res: any) => {
          resultado.push(...(res?.data ?? res ?? []));
          pendientes -= 1;
          if (pendientes === 0) {
            this.subcategorias = resultado;
            this.cdr.markForCheck();
          }
        },
        error: () => {
          pendientes -= 1;
          if (pendientes === 0) {
            this.subcategorias = resultado;
            this.cdr.markForCheck();
          }
        }
      });
    });
  }

  cambiarTab(tab: CategoriaTab): void {
    this.tab = tab;
    this.busqueda = '';
    this.cdr.markForCheck();
  }

  guardar(): void {
    if (this.tab === 'categorias') {
      this.guardarCategoria();
    } else {
      this.guardarSubcategoria();
    }
  }

  guardarCategoria(): void {
    if (!this.categoriaForm.categoria_nombre.trim()) {
      this.mostrarError('El nombre es obligatorio.');
      return;
    }

    this.guardando = true;
    const payload = new FormData();
    payload.append('categoria_nombre', this.categoriaForm.categoria_nombre.trim());
    payload.append('categoria_descripcion', this.categoriaForm.categoria_descripcion.trim());

    if (this.categoriaImagen) {
      payload.append('categoria_imagen', this.categoriaImagen);
    }

    const request = this.editandoCategoriaId
      ? this.adminService.actualizar('categorias', this.editandoCategoriaId, payload)
      : this.adminService.crear('categorias', payload);

    request.subscribe({
      next: () => this.finalizarGuardado('Categoria guardada.'),
      error: (err) => this.manejarError(err, 'No se pudo guardar la categoria.')
    });
  }

  guardarSubcategoria(): void {
    if (!this.subcategoriaForm.subcategoria_nombre.trim() || !this.subcategoriaForm.categoria_id) {
      this.mostrarError('El nombre y la categoria son obligatorios.');
      return;
    }

    this.guardando = true;
    const payload = new FormData();
    payload.append('subcategoria_nombre', this.subcategoriaForm.subcategoria_nombre.trim());
    payload.append('categoria_id', String(Number(this.subcategoriaForm.categoria_id)));
    payload.append('subcategoria_descripcion', this.subcategoriaForm.subcategoria_descripcion.trim());

    const request = this.editandoSubcategoriaId
      ? this.adminService.actualizarSubcategoria(this.editandoSubcategoriaId, payload)
      : this.adminService.crearSubcategoria(payload);

    request.subscribe({
      next: () => this.finalizarGuardado('Subcategoria guardada.'),
      error: (err) => this.manejarError(err, 'No se pudo guardar la subcategoria.')
    });
  }

  editarCategoria(categoria: any): void {
    this.tab = 'categorias';
    this.editandoCategoriaId = Number(categoria.categoria_id);
    this.categoriaForm = {
      categoria_nombre: categoria.categoria_nombre ?? '',
      categoria_descripcion: categoria.categoria_descripcion ?? ''
    };
    this.categoriaPreview = categoria.categoria_imagen ?? null;
  }

  editarSubcategoria(subcategoria: any): void {
    this.tab = 'subcategorias';
    this.editandoSubcategoriaId = Number(subcategoria.subcategoria_id);
    this.subcategoriaForm = {
      subcategoria_nombre: subcategoria.subcategoria_nombre ?? '',
      categoria_id: String(subcategoria.subcategoria_categoria_id ?? subcategoria.categoria_id ?? ''),
      subcategoria_descripcion: subcategoria.subcategoria_descripcion ?? ''
    };
  }

  eliminarCategoria(categoria: any): void {
    if (!confirm('Seguro que quieres eliminar esta categoria?')) return;

    this.adminService.eliminar('categorias', Number(categoria.categoria_id)).subscribe({
      next: () => this.finalizarGuardado('Categoria eliminada.'),
      error: (err) => this.manejarError(err, 'No se pudo eliminar la categoria.')
    });
  }

  eliminarSubcategoria(subcategoria: any): void {
    if (!confirm('Seguro que quieres eliminar esta subcategoria?')) return;

    this.adminService.eliminarSubcategoria(Number(subcategoria.subcategoria_id)).subscribe({
      next: () => this.finalizarGuardado('Subcategoria eliminada.'),
      error: (err) => this.manejarError(err, 'No se pudo eliminar la subcategoria.')
    });
  }

  cancelar(): void {
    this.editandoCategoriaId = null;
    this.editandoSubcategoriaId = null;
    this.categoriaForm = { categoria_nombre: '', categoria_descripcion: '' };
    this.subcategoriaForm = { subcategoria_nombre: '', categoria_id: '', subcategoria_descripcion: '' };
    this.categoriaImagen = null;
    this.categoriaPreview = null;
    this.error = '';
  }

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      this.mostrarError('Solo se permiten imagenes JPG, PNG o WEBP.');
      input.value = '';
      return;
    }

    const preview = URL.createObjectURL(file);
    this.categoriaImagen = file;
    this.categoriaPreview = preview;
  }

  get categoriasFiltradas(): any[] {
    const termino = this.busqueda.toLowerCase().trim();
    return this.categorias.filter((categoria) =>
      !termino || [categoria.categoria_id, categoria.categoria_nombre, categoria.categoria_descripcion]
        .some((valor) => String(valor ?? '').toLowerCase().includes(termino))
    );
  }

  get subcategoriasFiltradas(): any[] {
    const termino = this.busqueda.toLowerCase().trim();
    return this.subcategorias.filter((subcategoria) =>
      !termino || [
        subcategoria.subcategoria_id,
        subcategoria.subcategoria_nombre,
        this.nombreCategoria(subcategoria.subcategoria_categoria_id ?? subcategoria.categoria_id),
        subcategoria.subcategoria_descripcion
      ].some((valor) => String(valor ?? '').toLowerCase().includes(termino))
    );
  }

  nombreCategoria(categoriaId: number | string): string {
    return this.categorias.find((categoria) => Number(categoria.categoria_id) === Number(categoriaId))?.categoria_nombre ?? '';
  }

  get tituloGestion(): string {
    return this.tab === 'subcategorias' ? 'Gestion de subcategorias' : 'Gestion de categorias';
  }

  imagenUrl(url: string | null | undefined): string {
    return this.adminService.resolverUrlImagen(url);
  }

  private finalizarGuardado(mensaje: string): void {
    this.mostrarMensaje(mensaje);
    this.guardando = false;
    this.cancelar();
    this.cargarCategorias();
  }

  private manejarError(err: any, mensaje: string): void {
    this.mostrarError(err?.error?.message || err?.error?.error || mensaje);
    this.guardando = false;
    this.cdr.markForCheck();
  }

  private mostrarMensaje(mensaje: string): void {
    this.mensaje = mensaje;
    this.error = '';
    this.programarOcultarAviso();
  }

  private mostrarError(error: string): void {
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
      this.cdr.markForCheck();
    }, 3500);
  }

  private limpiarAvisoTimer(): void {
    if (this.avisoTimer) {
      clearTimeout(this.avisoTimer);
      this.avisoTimer = null;
    }
  }
}
