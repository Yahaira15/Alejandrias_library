import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig, AdminField } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-foros',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-foros.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss', './admin-foros.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminForos extends AdminCrudBase implements OnInit {
  categorias: any[] = [];
  subcategorias: any[] = [];
  usuarios: any[] = [];
  passwordVisible = false;

  config: AdminCrudConfig = {
    titulo: 'Gestion de foros',
    recurso: 'foros',
    idKey: 'foro_id',
    searchKeys: ['foro_titulo', 'foro_descripcion', 'foro_categoria_id', 'foro_creador_id'],
    fields: [
      { key: 'foro_titulo', label: 'Titulo', required: true },
      { key: 'foro_categoria_id', label: 'Categoria', type: 'select', required: true },
      { key: 'subcategoria_id', label: 'Subcategoria', type: 'select' },
      { key: 'foro_creador_id', label: 'Creador', type: 'select', required: true },
      { key: 'foro_privado', label: 'Privado', type: 'checkbox' },
      { key: 'foro_password', label: 'Contrasena privada', type: 'password', hideInTable: true },
      { key: 'foro_descripcion', label: 'Descripcion', type: 'textarea', required: true },
      { key: 'foro_imagen', label: 'Imagen', type: 'image' }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarOpciones();
  }

  override resetFormulario(): void {
    super.resetFormulario();
    this.formulario.foro_creador_id = this.usuarioActualId();
    this.formulario.foro_privado = true;
    this.formulario.foro_password = '';
    this.formulario.subcategoria_id = '';
    this.passwordVisible = false;
  }

  override nuevo(): void {
    super.nuevo();
    this.cargarSubcategorias();
  }

  override editar(registro: any): void {
    super.editar(registro);
    this.formulario.foro_password = '';
    this.formulario.subcategoria_id = registro.subcategoria_id ?? '';
    this.cargarSubcategorias(false);
  }

  override guardar(): void {
    if (!this.formulario.foro_titulo?.trim() || !this.formulario.foro_descripcion?.trim() || !this.formulario.foro_categoria_id) {
      this.mostrarError('El nombre, la categoria y la descripcion son obligatorios.');
      this.cdr.detectChanges();
      return;
    }

    if (!this.formulario.foro_creador_id) {
      this.formulario.foro_creador_id = this.usuarioActualId();
    }

    if (this.formulario.foro_privado && !this.editandoId && !/^[A-Za-z0-9]{8}$/.test(this.formulario.foro_password || '')) {
      this.mostrarError('La contrasena debe tener 8 caracteres alfanumericos.');
      this.cdr.detectChanges();
      return;
    }

    if (this.formulario.foro_privado && this.formulario.foro_password && !/^[A-Za-z0-9]{8}$/.test(this.formulario.foro_password)) {
      this.mostrarError('La contrasena debe tener 8 caracteres alfanumericos.');
      this.cdr.detectChanges();
      return;
    }

    if (!this.formulario.foro_privado) {
      this.formulario.foro_password = '';
    }

    super.guardar();
  }

  override esRequerido(field: AdminField): boolean {
    if (field.key === 'foro_password') {
      return !this.editandoId && !!this.formulario.foro_privado;
    }

    return super.esRequerido(field);
  }

  override valorCelda(registro: any, columna: AdminField): string {
    if (columna.key === 'foro_categoria_id') {
      return registro.categoria?.categoria_nombre || this.nombreCategoria(registro.foro_categoria_id) || 'Sin categoria';
    }

    if (columna.key === 'subcategoria_id') {
      return registro.subcategoria?.subcategoria_nombre || this.nombreSubcategoria(registro.subcategoria_id) || 'Sin subcategoria';
    }

    if (columna.key === 'foro_creador_id') {
      return this.nombreUsuario(registro.usuario) || this.nombreCreador(registro.foro_creador_id) || 'Sin creador';
    }

    return super.valorCelda(registro, columna);
  }

  override get filtrados(): any[] {
    const termino = this.busqueda.toLowerCase().trim();
    if (!termino) return this.registros;

    return this.registros.filter((registro) => [
      registro.foro_id,
      registro.foro_titulo,
      registro.foro_descripcion,
      registro.categoria?.categoria_nombre,
      this.nombreCategoria(registro.foro_categoria_id),
      registro.subcategoria?.subcategoria_nombre,
      this.nombreSubcategoria(registro.subcategoria_id),
      this.nombreUsuario(registro.usuario),
      this.nombreCreador(registro.foro_creador_id)
    ].some((valor) => String(valor ?? '').toLowerCase().includes(termino)));
  }

  get imagenField(): AdminField {
    return this.config.fields.find((field) => field.key === 'foro_imagen')!;
  }

  setPrivado(privado: boolean): void {
    this.formulario.foro_privado = privado;

    if (!privado) {
      this.formulario.foro_password = '';
      this.passwordVisible = false;
    }
  }

  togglePasswordVisibility(): void {
    this.passwordVisible = !this.passwordVisible;
  }

  cambiarCategoria(): void {
    this.formulario.subcategoria_id = '';
    this.cargarSubcategorias();
  }

  private cargarOpciones(): void {
    this.adminService.listar('categorias').subscribe({
      next: (res: any) => {
        this.categorias = res?.data ?? res ?? [];
        this.cargarSubcategorias();
        this.cdr.detectChanges();
      },
      error: () => {
        this.categorias = [];
        this.cdr.detectChanges();
      }
    });

    this.adminService.listar('usuarios').subscribe({
      next: (res: any) => {
        this.usuarios = res?.data ?? res ?? [];
        this.cdr.detectChanges();
      },
      error: () => {
        this.usuarios = [];
        this.cdr.detectChanges();
      }
    });
  }

  private cargarSubcategorias(limpiarSiNoHayCategoria = true): void {
    const categoriaId = Number(this.formulario.foro_categoria_id);

    if (!categoriaId) {
      this.subcategorias = [];
      if (limpiarSiNoHayCategoria) {
        this.formulario.subcategoria_id = '';
      }
      this.cdr.detectChanges();
      return;
    }

    this.adminService.listarSubcategoriasCategoria(categoriaId).subscribe({
      next: (res: any) => {
        this.subcategorias = res?.data ?? res ?? [];
        this.cdr.detectChanges();
      },
      error: () => {
        this.subcategorias = [];
        this.cdr.detectChanges();
      }
    });
  }

  private nombreCategoria(categoriaId: number | string): string {
    return this.categorias.find((categoria) => Number(categoria.categoria_id) === Number(categoriaId))?.categoria_nombre ?? '';
  }

  private nombreSubcategoria(subcategoriaId: number | string): string {
    return this.subcategorias.find((subcategoria) => Number(subcategoria.subcategoria_id) === Number(subcategoriaId))?.subcategoria_nombre ?? '';
  }

  private nombreCreador(usuarioId: number | string): string {
    const usuario = this.usuarios.find((item) => Number(item.usuario_id) === Number(usuarioId));
    return this.nombreUsuario(usuario);
  }

  private nombreUsuario(usuario: any): string {
    if (!usuario) return '';
    return `${usuario.usuario_nombre || ''} ${usuario.usuario_apellido || ''}`.trim() || usuario.usuario_apodo || usuario.usuario_email || '';
  }

  private usuarioActualId(): number | string {
    const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    return usuario.usuario_id || '';
  }
}
