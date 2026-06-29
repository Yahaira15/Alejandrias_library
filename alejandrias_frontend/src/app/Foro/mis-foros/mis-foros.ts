import { ChangeDetectionStrategy, ChangeDetectorRef, Component, HostListener, OnInit , OnDestroy} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ForoService } from '../../services/foro';
import { NotificacionService } from '../../services/notificacion.service';
import { ReportePayload, ReporteService } from '../../services/reporte.service';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { limpiarTokenSesion } from '../../services/auth-session';

@Component({
  selector: 'app-mis-foros',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './mis-foros.html',
  styleUrl: './mis-foros.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MisForos implements OnInit, OnDestroy {
  foros: any[] = [];
  cargando = false;
  usuario: any = null;
  rol = '';
  apodoUsuario = '';
  fotoPerfilUsuario = '';
  foroSeleccionado: any = null;
  modalForoAbierto = false;
  modalPrivadoAbierto = false;
  passwordPrivado = '';
  errorPrivado = '';
  registrandoForo = false;
  buscandoPrivado = false;
  eliminandoForoId: number | null = null;
  mensajeEliminacion: string = '';
  tipoMensajeEliminacion: 'success' | 'error' | '' = '';
  reporteModalAbierto = false;
  reporteObjetivo: { tipo: ReportePayload['reporte_tipo']; id: number; titulo: string } | null = null;
  reporteMotivo = '';
  reporteDescripcion = '';
  errorReporte = '';
  enviandoReporte = false;
  eliminandoSeguimientoId: number | null = null;
  favoritoProcesandoId: number | null = null;

  notificaciones: any[] = [];
  mostrarPanelNotificaciones = false;
  cantidadNoLeidas = 0;
  intervaloNotificaciones: any;
  sancionNotificacionModalAbierto = false;
  sancionNotificacionSeleccionada: any = null;

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private notificacionService: NotificacionService,
    private reporteService: ReporteService
  ) {}

  @HostListener('document:click')
  cerrarMenusForos(): void {
    if (!this.foros.some(foro => foro.menuAbierto)) return;
    this.foros = this.foros.map(foro => ({ ...foro, menuAbierto: false }));
    this.cdr.detectChanges();
  }

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.rol = this.usuario.usuario_rol;
    this.apodoUsuario = this.usuario.usuario_apodo || this.usuario.apodoUsuario || this.usuario.usuario_nombre || '';
    this.fotoPerfilUsuario = this.usuario.usuario_foto_perfil || '';
    this.cargarForos();
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

    this.foroService.getMisForos().subscribe({
      next: (res: any) => this.enriquecerForos(res?.data ?? res ?? []),
      error: (err) => {
        console.error('Error cargando mis foros:', err);
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
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

  irAInicio(): void {
    this.router.navigate(['/home']);
  }

  irAMisForos(): void {
    this.router.navigate(['/mis-foros']);
  }

  irACrearForo(): void {
    this.router.navigate(['/foros/crear']);
  }

  irAChatIa(): void {
    this.router.navigate(['/chat-ia']);
  }

  abrirModalForo(foro: any): void {
    this.foroSeleccionado = foro;
    this.modalForoAbierto = true;
    this.cdr.detectChanges();
  }

  cerrarModalForo(): void {
    this.modalForoAbierto = false;
    this.foroSeleccionado = null;
    this.registrandoForo = false;
    this.cdr.detectChanges();
  }

  entrarForoSeleccionado(): void {
    if (!this.foroSeleccionado) return;

    const foroId = this.foroSeleccionado.foro_id;
    this.cerrarModalForo();
    this.router.navigate(['/foros', foroId]);
  }

  verForo(foro: any): void {
    if (!foro?.foro_id) return;
    this.router.navigate(['/foros', foro.foro_id]);
  }

  toggleMenuForo(foro: any): void {
    this.foros = this.foros.map(item => ({
      ...item,
      menuAbierto: item.foro_id === foro.foro_id ? !item.menuAbierto : false
    }));
    this.cdr.detectChanges();
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

  editarForo(id: number): void {
    const foro = this.foros.find(item => item.foro_id === id);
    this.router.navigate(['/foros/editar', id], { state: { foro } });
  }

  eliminarForo(id: number): void {
    const confirmar = confirm('¿Deseas eliminar este foro?');
    if (!confirmar) return;

    this.eliminandoForoId = id;
    this.cdr.detectChanges();

    this.foroService.deleteForo(id).subscribe({
      next: () => {
        this.foros = this.foros.filter(f => f.foro_id !== id);
        this.mensajeEliminacion = 'Foro eliminado correctamente.';
        this.tipoMensajeEliminacion = 'success';
        this.eliminandoForoId = null;
        this.cdr.detectChanges();
        
        // Limpiar mensaje después de 5 segundos
        setTimeout(() => {
          this.mensajeEliminacion = '';
          this.tipoMensajeEliminacion = '';
          this.cdr.detectChanges();
        }, 5000);
      },
      error: (err) => {
        const mensajeError = this.extraerMensajeError(err);
        this.mensajeEliminacion = mensajeError;
        this.tipoMensajeEliminacion = 'error';
        this.eliminandoForoId = null;
        console.error('Error eliminando foro:', err);
        this.cdr.detectChanges();
        
        // Limpiar mensaje después de 7 segundos
        setTimeout(() => {
          this.mensajeEliminacion = '';
          this.tipoMensajeEliminacion = '';
          this.cdr.detectChanges();
        }, 7000);
      }
    });
  }

  private extraerMensajeError(err: any): string {
    if (err?.error?.mensaje) return err.error.mensaje;
    if (err?.error?.error) return err.error.error;
    if (err?.error?.detalle) return err.error.detalle;
    if (err?.message) return err.message;
    if (err?.status === 403) return 'No tienes permiso para eliminar este foro';
    if (err?.status === 404) return 'El foro no fue encontrado';
    if (err?.status === 401) return 'Debes estar autenticado para eliminar foros';
    return 'No se pudo eliminar el foro. Intenta de nuevo.';
  }

  dejarDeSeguirForo(foro: any): void {
    if (!foro?.foro_id || this.eliminandoSeguimientoId) return;

    const confirmar = confirm(`Deseas eliminar "${foro.foro_titulo}" de tu lista de seguimiento?`);
    if (!confirmar) return;

    this.eliminandoSeguimientoId = foro.foro_id;

    this.foroService.dejarDeSeguirForo(foro.foro_id).subscribe({
      next: () => {
        this.foros = this.foros.filter(item => item.foro_id !== foro.foro_id);
        this.eliminandoSeguimientoId = null;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando seguimiento', err);
        this.eliminandoSeguimientoId = null;
        alert(err?.error?.error || 'No se pudo eliminar el foro de tu lista.');
        this.cdr.detectChanges();
      }
    });
  }

  toggleFavorito(foro: any, event: Event): void {
    event.stopPropagation();

    if (!foro?.foro_id || this.favoritoProcesandoId) return;

    this.favoritoProcesandoId = foro.foro_id;
    foro.menuAbierto = false;

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

  abrirReporte(tipo: ReportePayload['reporte_tipo'], id: number, titulo: string): void {
    this.cerrarMenusForos();
    this.reporteObjetivo = { tipo, id, titulo };
    this.reporteMotivo = '';
    this.reporteDescripcion = '';
    this.errorReporte = '';
    this.reporteModalAbierto = true;
    this.cdr.detectChanges();
  }

  cerrarReporte(): void {
    this.reporteModalAbierto = false;
    this.reporteObjetivo = null;
    this.reporteMotivo = '';
    this.reporteDescripcion = '';
    this.errorReporte = '';
    this.enviandoReporte = false;
    this.cdr.detectChanges();
  }

  enviarReporte(): void {
    if (!this.reporteObjetivo || !this.reporteMotivo || this.enviandoReporte) {
      this.errorReporte = 'Selecciona un motivo para reportar.';
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
        this.errorReporte = err?.error?.error || 'No se pudo enviar el reporte.';
        this.cdr.detectChanges();
      }
    });
  }

  motivosReporte(): string[] {
    return ['tematica ilegal', 'contenido extremista', 'spam', 'fraude'];
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
    limpiarTokenSesion();
    this.router.navigate(['/login']);
  }

  
}
