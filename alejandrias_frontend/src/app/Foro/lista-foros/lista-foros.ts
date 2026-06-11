import { ChangeDetectionStrategy, ChangeDetectorRef, Component, HostListener, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ForoService } from '../../services/foro';
import { NotificacionService } from '../../services/notificacion.service';
import { ReportePayload, ReporteService } from '../../services/reporte.service';
import { Router, RouterLink } from '@angular/router';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

@Component({
  selector: 'app-lista-foros',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './lista-foros.html',
  styleUrls: ['./lista-foros.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ListaForos implements OnInit, OnDestroy {
  foros: any[] = [];
  categorias: any[] = [];
  cargando = false;
  usuario: any;
  rol = '';
  terminoBusqueda = '';
  categoriaSeleccionada: number | null = null;
  temaSeleccionado = '';
  busquedaActiva = false;
  mostrarPanelBusqueda = false;
  subcategoriasPorCategoria: Record<string, string[]> = {
    ciencias: ['Biologia', 'Fisica', 'Quimica'],
    tecnologia: ['Programacion', 'Inteligencia Artificial', 'Ciberseguridad'],
    humanidades: ['Historia', 'Filosofia', 'Literatura'],
    matematicas: ['Algebra', 'Geometria', 'Estadistica']
  };

  notificaciones: any[] = [];
  mostrarPanelNotificaciones = false;
  cantidadNoLeidas = 0;
  intervaloNotificaciones: any;

  apodoUsuario = '';
  fotoPerfilUsuario = '';
  foroSeleccionado: any = null;
  modalForoAbierto = false;
  modalPrivadoAbierto = false;
  passwordPrivado = '';
  errorPrivado = '';
  errorRegistro = '';
  registrandoForo = false;
  registrandoForoId: number | null = null;
  favoritoProcesandoId: number | null = null;
  buscandoPrivado = false;
  reporteModalAbierto = false;
  reporteObjetivo: { tipo: ReportePayload['reporte_tipo']; id: number; titulo: string } | null = null;
  reporteMotivo = '';
  reporteDescripcion = '';
  enviandoReporte = false;
  sancionNotificacionModalAbierto = false;
  sancionNotificacionSeleccionada: any = null;

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private notificacionService: NotificacionService,
    private reporteService: ReporteService
  ) {}

  @HostListener('document:click', ['$event'])
  cerrarPanelBusquedaSiClickFuera(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    const clickDentroBusqueda = target?.closest('.search-box, .search-panel, .clear-search, .topics-panel');

    if (clickDentroBusqueda) return;

    if (this.mostrarPanelBusqueda || this.busquedaActiva) {
      this.mostrarPanelBusqueda = false;
      this.busquedaActiva = false;
      this.cdr.detectChanges();
    }
  }

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.rol = this.usuario.usuario_rol;
    this.apodoUsuario = this.usuario.usuario_apodo || this.usuario.apodoUsuario || this.usuario.usuario_nombre || '';
    this.fotoPerfilUsuario = this.usuario.usuario_foto_perfil || '';
    this.cargarForos();
    this.cargarCategorias();
    this.cargarNotificaciones();
    this.intervaloNotificaciones = setInterval(() => {
      this.cargarNotificaciones();
    }, 15000);
  }

  ngOnDestroy(): void {
    if (this.intervaloNotificaciones) {
      clearInterval(this.intervaloNotificaciones);
    }
  }

  cargarForos(): void {
    this.cargando = true;

    this.foroService.getForosPublicos().subscribe({
      next: (res: any) => this.enriquecerForos(res ?? []),
      error: (err) => {
        console.error('ERROR FOROS PUBLICOS:', err);
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  cargarCategorias(): void {
    this.foroService.getCategorias().subscribe({
      next: (res: any) => {
        this.categorias = res ?? [];
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando categorÃ­as', err);
      }
    });
  }

  get forosFiltrados(): any[] {
    const termino = this.normalizarTexto(this.terminoBusqueda);

    return this.foros.filter((foro) => {
      const coincideNombre = !termino || this.normalizarTexto(foro.foro_titulo).includes(termino);
      const coincideCategoria = !this.categoriaSeleccionada || this.obtenerCategoriaId(foro) === this.categoriaSeleccionada;

      return coincideNombre && coincideCategoria;
    });
  }

  get sugerenciasForos(): any[] {
    const termino = this.normalizarTexto(this.terminoBusqueda);

    if (!termino) return [];

    return this.foros
      .filter((foro) => this.normalizarTexto(foro.foro_titulo).includes(termino))
      .slice(0, 5);
  }

  get nombreCategoriaSeleccionada(): string {
    return this.categorias.find((categoria) => categoria.categoria_id === this.categoriaSeleccionada)?.categoria_nombre || '';
  }

  get hayFiltrosActivos(): boolean {
    return !!this.terminoBusqueda.trim() || !!this.categoriaSeleccionada || !!this.temaSeleccionado;
  }

  get categoriasTematicas(): any[] {
    return this.categorias.map((categoria) => {
      const nombre = categoria.categoria_nombre || '';
      const clave = this.normalizarTexto(nombre);

      return {
        ...categoria,
        subcategorias: this.subcategoriasPorCategoria[clave] || ['General', 'Preguntas', 'Recursos']
      };
    });
  }

  seleccionarSugerencia(foro: any): void {
    this.terminoBusqueda = foro.foro_titulo;
    this.busquedaActiva = false;
    this.cdr.detectChanges();
  }

  seleccionarCategoria(categoriaId: number | null): void {
    this.categoriaSeleccionada = categoriaId;
    this.temaSeleccionado = '';
    this.mostrarPanelBusqueda = true;
    this.cdr.detectChanges();
  }

  seleccionarTema(categoria: any, tema: string): void {
    this.categoriaSeleccionada = categoria?.categoria_id ?? null;
    this.temaSeleccionado = tema;
    this.mostrarPanelBusqueda = false;
    this.busquedaActiva = false;
    this.cdr.detectChanges();
  }

  limpiarBusqueda(): void {
    this.terminoBusqueda = '';
    this.categoriaSeleccionada = null;
    this.temaSeleccionado = '';
    this.busquedaActiva = false;
    this.mostrarPanelBusqueda = false;
    this.cdr.detectChanges();
  }

  abrirPanelBusqueda(): void {
    this.busquedaActiva = true;
    this.mostrarPanelBusqueda = true;
  }

  ocultarSugerencias(): void {
    setTimeout(() => {
      this.busquedaActiva = false;
      this.cdr.detectChanges();
    }, 140);
  }

  private obtenerCategoriaId(foro: any): number | null {
    return Number(foro?.foro_categoria_id ?? foro?.categoria?.categoria_id) || null;
  }

  private normalizarTexto(valor: string | null | undefined): string {
    return (valor || '')
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  enriquecerForos(foros: any[]): void {
    if (!foros.length) {
      this.foros = [];
      this.cargando = false;
      this.cdr.detectChanges();
      return;
    }

    this.foros = foros.map((foro) => ({
      ...foro,
      publicaciones_count: foro.publicaciones_count || 0,
      comentarios_count_total: foro.comentarios_count_total || 0
    }));
    this.cargando = false;
    this.cdr.detectChanges();
  }

  irACrearForo(): void {
    this.router.navigate(['/foros/crear']);
  }

  irAInicio(): void {
    this.router.navigate(['/home']);
  }

  irAMisForos(): void {
    this.router.navigate(['/mis-foros']);
  }

  irAChatIa(): void {
    this.router.navigate(['/chat-ia']);
  }

  editarForo(id: number): void {
    const foro = this.foros.find(item => item.foro_id === id);
    this.router.navigate(['/foros/editar', id], { state: { foro } });
  }

  verForo(foro: any): void {
    if (!foro?.foro_id) return;
    this.router.navigate(['/foros', foro.foro_id]);
  }

  seguirForo(foro: any, event: Event): void {
    event.stopPropagation();

    if (!foro?.foro_id || this.registrandoForoId) return;

    this.registrandoForoId = foro.foro_id;
    this.errorRegistro = '';

    this.foroService.registrarEnForo(foro.foro_id).subscribe({
      next: () => {
        this.registrandoForoId = null;
        this.foros = this.foros.filter(item => item.foro_id !== foro.foro_id);
        this.router.navigate(['/foros', foro.foro_id]);
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error registrando foro', err);
        this.registrandoForoId = null;
        this.errorRegistro = err?.error?.error || 'No se pudo registrar al foro.';
        alert(this.errorRegistro);
        this.cdr.detectChanges();
      }
    });
  }

  toggleFavorito(foro: any, event: Event): void {
    event.stopPropagation();

    if (!foro?.foro_id || this.favoritoProcesandoId) return;

    this.favoritoProcesandoId = foro.foro_id;

    this.foroService.toggleFavoritoForo(foro.foro_id).subscribe({
      next: (res: any) => {
        foro.mi_favorito = !!res?.mi_favorito;
        foro.foro_favoritos_count = res?.foro_favoritos_count ?? foro.foro_favoritos_count ?? 0;
        this.favoritoProcesandoId = null;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando favorito', err);
        this.favoritoProcesandoId = null;
        alert(err?.error?.error || 'No se pudo actualizar el favorito.');
        this.cdr.detectChanges();
      }
    });
  }

  obtenerImagenForo(foro: any): string {
    const imagen = foro?.foro_imagen_url || foro?.foro_imagen || foro?.imagen || '';

    if (!imagen || foro?.imagenFallida) {
      return '';
    }

    return this.foroService.resolverImagenForo(imagen);
  }

  foroTieneImagen(foro: any): boolean {
    return !!this.obtenerImagenForo(foro);
  }

  marcarImagenFallida(foro: any): void {
    foro.imagenFallida = true;
    this.cdr.detectChanges();
  }

  abrirModalForo(foro: any): void {
    if (foro?.foro_privado && (this.rol === 'lider' || this.rol === 'admin')) {
      this.router.navigate(['/foros', foro.foro_id]);
      return;
    }

    this.foroSeleccionado = foro;
    this.modalForoAbierto = true;
    this.errorRegistro = '';
    this.cdr.detectChanges();
  }

  cerrarModalForo(): void {
    this.modalForoAbierto = false;
    this.foroSeleccionado = null;
    this.registrandoForo = false;
    this.errorRegistro = '';
    this.cdr.detectChanges();
  }

  abrirReporte(tipo: ReportePayload['reporte_tipo'], id: number, titulo: string): void {
    this.reporteObjetivo = { tipo, id, titulo };
    this.reporteMotivo = '';
    this.reporteDescripcion = '';
    this.reporteModalAbierto = true;
    this.cdr.detectChanges();
  }

  cerrarReporte(): void {
    this.reporteModalAbierto = false;
    this.reporteObjetivo = null;
    this.reporteMotivo = '';
    this.reporteDescripcion = '';
    this.enviandoReporte = false;
    this.cdr.detectChanges();
  }

  enviarReporte(): void {
    if (!this.reporteObjetivo || !this.reporteMotivo || this.enviandoReporte) {
      this.errorRegistro = 'Selecciona un motivo para reportar.';
      this.cdr.detectChanges();
      return;
    }

    this.enviandoReporte = true;
    this.reporteService.crearReporte({
      reporte_tipo: this.reporteObjetivo.tipo,
      reporte_referencia_id: this.reporteObjetivo.id,
      reporte_motivo: this.reporteMotivo,
      reporte_descripcion: this.reporteDescripcion
    }).subscribe({
      next: () => {
        this.cerrarReporte();
        alert('Reporte enviado a administracion.');
      },
      error: (err) => {
        this.enviandoReporte = false;
        this.errorRegistro = err?.error?.error || 'No se pudo enviar el reporte.';
        this.cdr.detectChanges();
      }
    });
  }

  motivosReporte(tipo: ReportePayload['reporte_tipo'] | undefined): string[] {
    if (tipo === 'usuario') return ['acoso', 'suplantacion', 'amenazas', 'spam masivo'];
    return ['tematica ilegal', 'contenido extremista', 'spam', 'fraude'];
  }

  registrarForoPublico(): void {
    if (!this.foroSeleccionado || this.registrandoForo) return;

    const confirmar = confirm(`¿Deseas registrarte al foro ${this.foroSeleccionado.foro_titulo}?`);
    if (!confirmar) return;

    this.registrandoForo = true;

    this.foroService.registrarEnForo(this.foroSeleccionado.foro_id).subscribe({
      next: () => {
        const foroId = this.foroSeleccionado.foro_id;
        this.cerrarModalForo();
        this.router.navigate(['/foros', foroId]);
      },
      error: (err) => {
        console.error('Error registrando foro', err);
        this.errorRegistro = err?.error?.error || 'No se pudo registrar al foro.';
        this.registrandoForo = false;
        this.cdr.detectChanges();
      }
    });
  }

  abrirModalPrivado(): void {
    this.modalPrivadoAbierto = true;
    this.passwordPrivado = '';
    this.errorPrivado = '';
    this.cdr.detectChanges();
  }

  cerrarModalPrivado(): void {
    this.modalPrivadoAbierto = false;
    this.passwordPrivado = '';
    this.errorPrivado = '';
    this.buscandoPrivado = false;
    this.cdr.detectChanges();
  }

  ingresarForoPrivado(): void {
    const password = this.passwordPrivado.trim();

    if (!password) {
      this.errorPrivado = 'Ingresa la contraseña del foro privado.';
      this.cdr.detectChanges();
      return;
    }

    if (!/^[A-Za-z0-9]{8}$/.test(password)) {
      this.errorPrivado = 'La contraseña debe tener 8 caracteres alfanuméricos.';
      this.cdr.detectChanges();
      return;
    }

    this.errorPrivado = '';
    this.buscandoPrivado = true;

    this.foroService.buscarForoPrivado(password).subscribe({
      next: (foro) => {
        this.buscandoPrivado = false;
        const confirmar = confirm(`¿Deseas registrarte al foro ${foro.foro_titulo}?`);

        if (!confirmar) {
          this.cdr.detectChanges();
          return;
        }

        this.foroService.registrarEnForo(foro.foro_id, password).subscribe({
          next: () => {
            this.cerrarModalPrivado();
            this.router.navigate(['/foros', foro.foro_id]);
          },
          error: (err) => {
            console.error('Error registrando foro privado', err);
            this.errorPrivado = err?.error?.error || 'No se pudo registrar al foro privado.';
            this.cdr.detectChanges();
          }
        });
      },
      error: (err) => {
        this.buscandoPrivado = false;
        this.errorPrivado = err?.error?.error || 'No se encontró un foro con esa contraseña.';
        this.cdr.detectChanges();
      }
    });
  }

  eliminarForo(id: number): void {
    const confirmar = confirm('�Estas seguro de que quieres eliminar este foro?');
    if (!confirmar) return;

    this.foroService.deleteForo(id).subscribe({
      next: () => {
        this.foros = this.foros.filter(f => f.foro_id !== id);
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando foro', err);
      }
    });
  }

  cargarNotificaciones(): void {

  this.notificacionService.getNotificaciones()
    .subscribe({

      next: (res) => {

        this.notificaciones = res;

      },

      error: (err) => {

        console.error('Error cargando notificaciones', err);

      }

    });

  this.notificacionService.getCantidadNoLeidas()
    .subscribe({

      next: (res) => {

        this.cantidadNoLeidas = res.cantidad;

      }

    });
  }

  toggleNotificaciones(): void {

    this.mostrarPanelNotificaciones =
      !this.mostrarPanelNotificaciones;
  }

  abrirNotificacion(notificacion: any): void {
    if (this.esNotificacionSancion(notificacion)) {
      this.verMasSancion(notificacion);
      return;
    }

    // ✅ Marcar leída
    this.notificacionService
      .marcarLeida(notificacion.notificacion_id)
      .subscribe({

        next: () => {

          notificacion.notificacion_leida = true;

          if (this.cantidadNoLeidas > 0) {
            this.cantidadNoLeidas--;
          }

          // 🚀 Navegar
          this.router.navigateByUrl(
            this.notificacionService.resolverDestino(notificacion)
          );

          this.mostrarPanelNotificaciones = false;
        }

      });
  }

  obtenerTiempo(fecha: string): string {

    return formatDistanceToNow(
      new Date(fecha),
      {
        addSuffix: true,
        locale: es
      }
    );
  }

  marcarComoLeida(notificacion: any): void {

    this.notificacionService.marcarComoLeida(
        notificacion.notificacion_id
      )
      .subscribe({

        next: () => {

          notificacion.notificacion_leida = true;

        },

        error: (err) => {

          console.error(err);

        }
      });
  }

  irANotificacion(notificacion: any): void {
    if (this.esNotificacionSancion(notificacion)) {
      this.verMasSancion(notificacion);
      return;
    }

    if (!notificacion.notificacion_leida) {

      this.marcarComoLeida(notificacion);

    }

    switch (notificacion.notificacion_tipo) {

      case 'registro_foro':

        this.router.navigate([
          '/foros',
          notificacion.notificacion_referencia_id
        ]);

        break;
      
      case 'nuevo_miembro':

        this.router.navigate([
          '/foros/',
          notificacion.notificacion_referencia_id
        ]);

        break;

      case 'nueva_publicacion':
        this.router.navigate([
          '/publicaciones',
          notificacion.notificacion_referencia_id
        ]);

        break;

      case 'nuevo_comentario':

        this.router.navigate([
          '/publicaciones',
          notificacion.notificacion_referencia_id
        ]);

        break;

      case 'nuevo_reporte':
        this.router.navigate(['/admin/reportes']);
        break;

      default:

        console.warn(
          'Tipo de notificación desconocido'
        );
    }

    this.mostrarPanelNotificaciones = false;
  }

  esNotificacionSancion(notificacion: any): boolean {
    return ['sancion_advertencia', 'sancion_restriccion'].includes(notificacion?.notificacion_tipo);
  }

  verMasSancion(notificacion: any): void {
    if (!notificacion.notificacion_leida) {
      this.notificacionService.marcarComoLeida(notificacion.notificacion_id).subscribe({
        next: () => {
          notificacion.notificacion_leida = true;
          if (this.cantidadNoLeidas > 0) {
            this.cantidadNoLeidas--;
          }
          this.cdr.detectChanges();
        },
        error: (err) => console.error(err)
      });
    }

    this.sancionNotificacionSeleccionada = notificacion;
    this.sancionNotificacionModalAbierto = true;
    this.mostrarPanelNotificaciones = false;
    this.cdr.detectChanges();
  }

  cerrarModalSancion(): void {
    this.sancionNotificacionModalAbierto = false;
    this.sancionNotificacionSeleccionada = null;
    this.cdr.detectChanges();
  }

  tituloSancion(notificacion: any): string {
    return notificacion?.notificacion_tipo === 'sancion_restriccion'
      ? 'Restriccion temporal'
      : 'Advertencia';
  }

  trackByForoId(index: number, foro: any): number {
    return foro.foro_id;
  }

  logout(): void {
    localStorage.removeItem('usuario');
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }
}
