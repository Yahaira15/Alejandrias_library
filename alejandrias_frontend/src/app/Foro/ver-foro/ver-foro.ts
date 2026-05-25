import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ForoService } from '../../services/foro';
import { ReportePayload, ReporteService } from '../../services/reporte.service';

@Component({
  selector: 'app-ver-foro',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './ver-foro.html',
  styleUrls: ['./ver-foro.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class VerForoComponent implements OnInit {
  foroId!: number;
  foro: any = null;
  publicaciones: any[] = [];
  archivosPublicacion: File[] = [];
  usuario: any = null;
  loading = false;
  loadingPublicaciones = false;
  creandoPublicacion = false;
  guardandoPublicacion = false;
  eliminandoPublicacionId: number | null = null;
  error = '';
  publicacionEditando: any = null;
  feedbackMensaje = '';
  feedbackTipo: 'success' | 'error' | '' = '';
  private feedbackTimeout: ReturnType<typeof setTimeout> | null = null;
  modalPasswordAbierto = false;
  usuarioPassword = '';
  passwordForoRevelada = '';
  errorPasswordForo = '';
  consultandoPasswordForo = false;
  copiandoPasswordForo = false;
  reporteModalAbierto = false;
  reporteObjetivo: { tipo: ReportePayload['reporte_tipo']; id: number; titulo: string } | null = null;
  reporteMotivo = '';
  reporteDescripcion = '';
  enviandoReporte = false;

  nuevaPublicacion = {
    publicacion_titulo: '',
    publicacion_contenido: ''
  };

  constructor(
    private route: ActivatedRoute,
    private foroService: ForoService,
    private reporteService: ReporteService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
    this.foroId = Number(this.route.snapshot.paramMap.get('foro_id'));
    this.cargarForo();
    this.cargarPublicaciones();
    this.cdr.detectChanges();
  }

  cargarForo(): void {
    this.loading = true;
    this.error = '';

    this.foroService.getForo(this.foroId).subscribe({
      next: (data) => {
        this.foro = data;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando foro:', err);
        this.error = 'No se pudo cargar la informacion del foro.';
        this.mostrarFeedback('error', 'No se pudo cargar la informacion del foro.');
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  cargarPublicaciones(): void {
    this.loadingPublicaciones = true;

    this.foroService.getPublicaciones(this.foroId).subscribe({
      next: (data) => {
        this.publicaciones = Array.isArray(data) ? data : [];
        this.loadingPublicaciones = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando publicaciones:', err);
        this.mostrarFeedback('error', 'No se pudieron cargar las publicaciones.');
        this.loadingPublicaciones = false;
        this.cdr.detectChanges();
      }
    });
  }

  crearPublicacion(): void {
    if (!this.puedeCrearPublicacion) {
      return;
    }

    const tieneAdjuntos = this.archivosPublicacion.length > 0;
    this.creandoPublicacion = true;

    this.foroService.crearPublicacion(this.foroId, this.nuevaPublicacion).subscribe({
      next: (publicacion) => {
        const visible = this.esContenidoVisible(publicacion);
        if (visible) {
          this.publicaciones = [publicacion, ...this.publicaciones];
        }
        this.nuevaPublicacion = {
          publicacion_titulo: '',
          publicacion_contenido: ''
        };
        this.creandoPublicacion = false;
        this.mostrarFeedback(
          visible ? 'success' : 'error',
          this.mensajeModeracion(publicacion, 'Publicacion creada correctamente.')
        );
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error creando publicacion:', err);
        this.creandoPublicacion = false;
        this.mostrarFeedback('error', 'No se pudo crear la publicacion.');
        this.cdr.detectChanges();
      }
    });
  }

  iniciarEdicionPublicacion(publicacion: any): void {
    this.publicacionEditando = {
      publicacion_id: publicacion.publicacion_id,
      publicacion_titulo: publicacion.publicacion_titulo,
      publicacion_contenido: publicacion.publicacion_contenido
    };
    this.cdr.detectChanges();
  }

  cancelarEdicionPublicacion(): void {
    this.publicacionEditando = null;
    this.guardandoPublicacion = false;
    this.cdr.detectChanges();
  }

  guardarEdicionPublicacion(): void {
    if (!this.publicacionEditando) {
      return;
    }

    if (
      !this.publicacionEditando.publicacion_titulo?.trim() ||
      !this.publicacionEditando.publicacion_contenido?.trim()
    ) {
      this.mostrarFeedback('error', 'Completa el titulo y el contenido de la publicacion.');
      return;
    }

    this.guardandoPublicacion = true;

    this.foroService.actualizarPublicacion(this.publicacionEditando.publicacion_id, {
      publicacion_titulo: this.publicacionEditando.publicacion_titulo,
      publicacion_contenido: this.publicacionEditando.publicacion_contenido
    }).subscribe({
      next: (publicacionActualizada) => {
        if (this.esContenidoVisible(publicacionActualizada)) {
          this.publicaciones = this.publicaciones.map(publicacion =>
            publicacion.publicacion_id === publicacionActualizada.publicacion_id
              ? { ...publicacion, ...publicacionActualizada }
              : publicacion
          );
        } else {
          this.publicaciones = this.publicaciones.filter(
            publicacion => publicacion.publicacion_id !== publicacionActualizada.publicacion_id
          );
        }
        this.publicacionEditando = null;
        this.guardandoPublicacion = false;
        this.mostrarFeedback(
          this.esContenidoVisible(publicacionActualizada) ? 'success' : 'error',
          this.mensajeModeracion(publicacionActualizada, 'Publicacion actualizada correctamente.')
        );
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando publicacion:', err);
        this.guardandoPublicacion = false;
        this.mostrarFeedback('error', 'No se pudo actualizar la publicacion.');
        this.cdr.detectChanges();
      }
    });
  }

  eliminarPublicacion(publicacionId: number): void {
    if (!confirm('¿Seguro que quieres eliminar esta publicacion?')) {
      return;
    }

    this.eliminandoPublicacionId = publicacionId;

    this.foroService.eliminarPublicacion(publicacionId).subscribe({
      next: () => {
        this.publicaciones = this.publicaciones.filter(
          publicacion => publicacion.publicacion_id !== publicacionId
        );
        this.eliminandoPublicacionId = null;
        this.mostrarFeedback('success', 'Publicacion eliminada correctamente.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando publicacion:', err);
        this.eliminandoPublicacionId = null;
        this.mostrarFeedback('error', 'No se pudo eliminar la publicacion.');
        this.cdr.detectChanges();
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
    }, 3200);
  }

  cerrarFeedback(): void {
    this.feedbackMensaje = '';
    this.feedbackTipo = '';

    if (this.feedbackTimeout) {
      clearTimeout(this.feedbackTimeout);
      this.feedbackTimeout = null;
    }

    this.cdr.detectChanges();
  }

  abrirModalPasswordForo(): void {
    this.modalPasswordAbierto = true;
    this.usuarioPassword = '';
    this.passwordForoRevelada = '';
    this.errorPasswordForo = '';
    this.consultandoPasswordForo = false;
    this.cdr.detectChanges();
  }

  cerrarModalPasswordForo(): void {
    this.modalPasswordAbierto = false;
    this.usuarioPassword = '';
    this.passwordForoRevelada = '';
    this.errorPasswordForo = '';
    this.consultandoPasswordForo = false;
    this.copiandoPasswordForo = false;
    this.cdr.detectChanges();
  }

  revelarPasswordForo(): void {
    const password = this.usuarioPassword.trim();

    if (!password) {
      this.errorPasswordForo = 'Ingresa tu contraseña de usuario.';
      this.cdr.detectChanges();
      return;
    }

    this.errorPasswordForo = '';
    this.passwordForoRevelada = '';
    this.consultandoPasswordForo = true;

    this.foroService.revelarPasswordForo(this.foroId, password).subscribe({
      next: (res) => {
        this.passwordForoRevelada = res?.foro_password || '';
        this.consultandoPasswordForo = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error revelando contraseña del foro:', err);
        this.errorPasswordForo = err?.error?.error || 'No se pudo mostrar la contraseña del foro.';
        this.consultandoPasswordForo = false;
        this.cdr.detectChanges();
      }
    });
  }

  copiarPasswordForo(): void {
    if (!this.passwordForoRevelada || this.copiandoPasswordForo) {
      return;
    }

    this.copiandoPasswordForo = true;

    navigator.clipboard.writeText(this.passwordForoRevelada)
      .then(() => {
        this.copiandoPasswordForo = false;
        this.mostrarFeedback('success', 'Contraseña del foro copiada.');
        this.cdr.detectChanges();
      })
      .catch(() => {
        this.copiandoPasswordForo = false;
        this.errorPasswordForo = 'No se pudo copiar la contraseña.';
        this.cdr.detectChanges();
      });
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
      this.mostrarFeedback('error', 'Selecciona un motivo para reportar.');
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
        this.mostrarFeedback('success', 'Reporte enviado a administracion.');
      },
      error: (err) => {
        console.error('Error enviando reporte:', err);
        this.enviandoReporte = false;
        this.mostrarFeedback('error', err?.error?.error || 'No se pudo enviar el reporte.');
        this.cdr.detectChanges();
      }
    });
  }

  motivosReporte(tipo: ReportePayload['reporte_tipo'] | undefined): string[] {
    switch (tipo) {
      case 'foro':
        return ['tematica ilegal', 'contenido extremista', 'spam', 'fraude'];
      case 'publicacion':
        return ['spam', 'insultos', 'contenido sexual', 'violencia', 'odio', 'acoso', 'informacion falsa', 'contenido ilegal'];
      case 'usuario':
        return ['acoso', 'suplantacion', 'amenazas', 'spam masivo'];
      default:
        return ['spam', 'insultos', 'acoso', 'contenido ilegal'];
    }
  }

  get esCreadorDelForo(): boolean {
    return !!this.usuario
      && !!this.foro
      && (
        this.usuario.usuario_rol === 'admin'
        || (this.usuario.usuario_id === this.foro.foro_creador_id && this.usuario.usuario_rol === 'lider')
      );
  }
  get puedeCrearPublicacion(): boolean {
    return !!this.usuario
      && this.nuevaPublicacion.publicacion_titulo.trim().length > 0
      && this.nuevaPublicacion.publicacion_contenido.trim().length > 0
      && !this.creandoPublicacion;
  }

  onAdjuntosPublicacionSeleccionados(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []);

    if (!archivos.length) {
      return;
    }

    this.archivosPublicacion = [...this.archivosPublicacion, ...archivos];
    input.value = '';
    this.cdr.detectChanges();
  }

  quitarAdjuntoPublicacion(index: number): void {
    this.archivosPublicacion = this.archivosPublicacion.filter((_, i) => i !== index);
    this.cdr.detectChanges();
  }

  trackByPublicacionId(index: number, publicacion: any): number {
    return publicacion.publicacion_id;
  }

  esPropietarioDePublicacion(publicacion: any): boolean {
    return !!this.usuario && (
      this.usuario.usuario_rol === 'admin'
      || this.usuario.usuario_id === publicacion.publicacion_usuario_id
    );
  }

  tiempoRelativo(fecha: string | null | undefined): string {
    if (!fecha) return 'Fecha desconocida';

    const valor = new Date(fecha);
    const diffSegundos = Math.floor((Date.now() - valor.getTime()) / 1000);

    if (Number.isNaN(valor.getTime())) return 'Fecha desconocida';
    if (diffSegundos < 60) return 'Hace unos segundos';

    const diffMinutos = Math.floor(diffSegundos / 60);
    if (diffMinutos < 60) return `Hace ${diffMinutos} min`;

    const diffHoras = Math.floor(diffMinutos / 60);
    if (diffHoras < 24) return `Hace ${diffHoras} h`;

    const diffDias = Math.floor(diffHoras / 24);
    if (diffDias < 7) return `Hace ${diffDias} dia${diffDias === 1 ? '' : 's'}`;

    const diffSemanas = Math.floor(diffDias / 7);
    if (diffSemanas < 5) return `Hace ${diffSemanas} semana${diffSemanas === 1 ? '' : 's'}`;

    const diffMeses = Math.floor(diffDias / 30);
    if (diffMeses < 12) return `Hace ${diffMeses} mes${diffMeses === 1 ? '' : 'es'}`;

    const diffAnos = Math.floor(diffDias / 365);
    return `Hace ${diffAnos} ano${diffAnos === 1 ? '' : 's'}`;
  }

  resumenContenido(contenido: string | null | undefined, limite: number = 180): string {
    if (!contenido) return '';
    if (contenido.length <= limite) return contenido;
    return `${contenido.slice(0, limite).trim()}...`;
  }

  private esContenidoVisible(item: any): boolean {
    const estadoIa = item?._moderacion?.estado;
    if (estadoIa && estadoIa !== 'permitido') {
      return false;
    }

    const estadoModeracion = item?.estado_moderacion;
    return !estadoModeracion || estadoModeracion === 'visible';
  }

  private mensajeModeracion(item: any, mensajeVisible: string): string {
    const estadoIa = item?._moderacion?.estado;
    const estadoModeracion = item?.estado_moderacion;

    if (estadoIa === 'revision' || estadoModeracion === 'revision') {
      return 'La publicacion fue enviada a revision por moderacion IA.';
    }

    if (estadoIa === 'bloqueado' || estadoModeracion === 'bloqueado') {
      return 'La publicacion fue bloqueada por moderacion IA.';
    }

    return mensajeVisible;
  }
}

