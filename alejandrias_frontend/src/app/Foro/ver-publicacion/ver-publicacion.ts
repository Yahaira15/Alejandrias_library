import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ForoService } from '../../services/foro';
import { ReportePayload, ReporteService } from '../../services/reporte.service';

@Component({
  selector: 'app-ver-publicacion',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './ver-publicacion.html',
  styleUrls: ['./ver-publicacion.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class VerPublicacionComponent implements OnInit {
  private readonly comentariosPorPagina = 5;
  publicacionId!: number;
  publicacion: any = null;
  comentarios: any[] = [];
  archivosComentario: File[] = [];
  comentariosVisiblesCount = this.comentariosPorPagina;
  usuario: any = null;
  loading = false;
  loadingComentarios = false;
  creandoComentario = false;
  guardandoComentario = false;
  eliminandoComentarioId: number | null = null;
  comentarioEditando: any = null;
  nuevoComentario = '';
  feedbackMensaje = '';
  feedbackTipo: 'success' | 'error' | '' = '';
  private feedbackTimeout: ReturnType<typeof setTimeout> | null = null;
  private comentarioDestino: string | null = null;
  reporteModalAbierto = false;
  reporteObjetivo: { tipo: ReportePayload['reporte_tipo']; id: number; titulo: string } | null = null;
  reporteMotivo = '';
  reporteDescripcion = '';
  enviandoReporte = false;

  constructor(
    private route: ActivatedRoute,
    private foroService: ForoService,
    private reporteService: ReporteService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
    this.publicacionId = Number(this.route.snapshot.paramMap.get('publicacion_id'));
    this.route.fragment.subscribe((fragment) => {
      this.comentarioDestino = fragment;
      this.desplazarAComentario();
    });
    this.cargarPublicacion();
    this.cargarComentarios();
    this.cdr.detectChanges();
  }

  cargarPublicacion(): void {
    this.loading = true;

    this.foroService.getPublicacion(this.publicacionId).subscribe({
      next: (data) => {
        this.publicacion = data;
        this.loading = false;
        this.registrarLectura();
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando publicacion:', err);
        this.loading = false;
        this.mostrarFeedback('error', 'No se pudo cargar la publicacion.');
        this.cdr.detectChanges();
      }
    });
  }

  private registrarLectura(): void {
    if (!this.usuario || !this.publicacionId) {
      return;
    }

    this.foroService.registrarLecturaPublicacion(this.publicacionId).subscribe({
      next: () => {},
      error: (err) => console.warn('No se pudo registrar lectura de publicacion:', err)
    });
  }

  cargarComentarios(): void {
    this.loadingComentarios = true;

    this.foroService.getComentariosPublicacion(this.publicacionId).subscribe({
      next: (data) => {
        this.comentarios = Array.isArray(data) ? data : [];
        this.comentariosVisiblesCount = this.comentariosPorPagina;
        this.loadingComentarios = false;
        this.cdr.detectChanges();
        this.desplazarAComentario();
      },
      error: (err) => {
        console.error('Error cargando comentarios:', err);
        this.loadingComentarios = false;
        this.mostrarFeedback('error', 'No se pudieron cargar los comentarios.');
        this.cdr.detectChanges();
      }
    });
  }

  crearComentario(): void {
    if (!this.puedeCrearComentario) {
      return;
    }

    const tieneAdjuntos = this.archivosComentario.length > 0;
    this.creandoComentario = true;

    this.foroService.crearComentarioPublicacion(this.publicacionId, this.nuevoComentario).subscribe({
      next: (comentario) => {
        const visible = this.esContenidoVisible(comentario);
        if (visible) {
          this.comentarios = [...this.comentarios, comentario];
          this.comentariosVisiblesCount = Math.max(this.comentariosVisiblesCount, this.comentarios.length);
        }
        this.nuevoComentario = '';
        this.creandoComentario = false;
        this.mostrarFeedback(
          visible ? 'success' : 'error',
          this.mensajeModeracion(comentario, 'Comentario enviado correctamente.')
        );
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error creando comentario:', err);
        this.creandoComentario = false;
        this.mostrarFeedback('error', this.mensajeModeracionDesdeError(err, 'No se pudo enviar el comentario.'));
        this.cdr.detectChanges();
      }
    });
  }

  iniciarEdicionComentario(comentario: any): void {
    this.comentarioEditando = {
      comentario_id: comentario.comentario_id,
      comentario_contenido: comentario.comentario_contenido
    };
    this.cdr.detectChanges();
  }

  cancelarEdicionComentario(): void {
    this.comentarioEditando = null;
    this.guardandoComentario = false;
    this.cdr.detectChanges();
  }

  guardarEdicionComentario(): void {
    if (!this.comentarioEditando?.comentario_contenido?.trim()) {
      this.mostrarFeedback('error', 'El comentario no puede estar vacio.');
      return;
    }

    this.guardandoComentario = true;

    this.foroService.actualizarComentario(
      this.comentarioEditando.comentario_id,
      this.comentarioEditando.comentario_contenido
    ).subscribe({
      next: (comentarioActualizado) => {
        if (this.esContenidoVisible(comentarioActualizado)) {
          this.comentarios = this.comentarios.map(comentario =>
            comentario.comentario_id === comentarioActualizado.comentario_id
              ? { ...comentario, ...comentarioActualizado }
              : comentario
          );
        } else {
          this.comentarios = this.comentarios.filter(
            comentario => comentario.comentario_id !== comentarioActualizado.comentario_id
          );
        }
        this.comentarioEditando = null;
        this.guardandoComentario = false;
        this.mostrarFeedback(
          this.esContenidoVisible(comentarioActualizado) ? 'success' : 'error',
          this.mensajeModeracion(comentarioActualizado, 'Comentario actualizado correctamente.')
        );
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando comentario:', err);
        this.guardandoComentario = false;
        this.mostrarFeedback('error', this.mensajeModeracionDesdeError(err, 'No se pudo actualizar el comentario.'));
        this.cdr.detectChanges();
      }
    });
  }

  eliminarComentario(comentarioId: number): void {
    if (!confirm('�Seguro que quieres eliminar este comentario?')) {
      return;
    }

    this.eliminandoComentarioId = comentarioId;

    this.foroService.eliminarComentario(comentarioId).subscribe({
      next: () => {
        this.comentarios = this.comentarios.filter(
          comentario => comentario.comentario_id !== comentarioId
        );
        this.eliminandoComentarioId = null;
        this.mostrarFeedback('success', 'Comentario eliminado correctamente.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error eliminando comentario:', err);
        this.eliminandoComentarioId = null;
        this.mostrarFeedback('error', 'No se pudo eliminar el comentario.');
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
    }, 6500);
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
      case 'publicacion':
        return ['spam', 'insultos', 'contenido sexual', 'violencia', 'odio', 'acoso', 'informacion falsa', 'contenido ilegal'];
      case 'comentario':
        return ['spam', 'insultos', 'contenido sexual', 'violencia', 'odio', 'acoso', 'contenido ilegal'];
      case 'usuario':
        return ['acoso', 'suplantacion', 'amenazas', 'spam masivo'];
      default:
        return ['spam', 'acoso', 'contenido ilegal'];
    }
  }

  get puedeCrearComentario(): boolean {
    return !!this.usuario && this.nuevoComentario.trim().length > 0 && !this.creandoComentario;
  }

  onAdjuntosComentarioSeleccionados(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []);

    if (!archivos.length) {
      return;
    }

    this.archivosComentario = [...this.archivosComentario, ...archivos];
    input.value = '';
    this.cdr.detectChanges();
  }

  quitarAdjuntoComentario(index: number): void {
    this.archivosComentario = this.archivosComentario.filter((_, i) => i !== index);
    this.cdr.detectChanges();
  }

  get comentariosVisibles(): any[] {
    return this.comentarios.slice(0, this.comentariosVisiblesCount);
  }

  get tieneMasComentarios(): boolean {
    return this.comentariosVisiblesCount < this.comentarios.length;
  }

  cargarMasComentarios(): void {
    this.comentariosVisiblesCount = Math.min(
      this.comentariosVisiblesCount + this.comentariosPorPagina,
      this.comentarios.length
    );
    this.cdr.detectChanges();
  }

  trackByComentarioId(index: number, comentario: any): number {
    return comentario.comentario_id;
  }

  private desplazarAComentario(): void {
    if (!this.comentarioDestino?.startsWith('comentario-')) {
      return;
    }

    const comentarioId = Number(this.comentarioDestino.replace('comentario-', ''));
    if (!comentarioId) {
      return;
    }

    const indice = this.comentarios.findIndex(item => item.comentario_id === comentarioId);
    if (indice >= 0) {
      this.comentariosVisiblesCount = Math.max(this.comentariosVisiblesCount, indice + 1);
      this.cdr.detectChanges();
    }

    setTimeout(() => {
      document.getElementById(this.comentarioDestino || '')?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
      });
    }, 80);
  }

  esComentarioPropio(comentario: any): boolean {
    return !!this.usuario && (
      this.usuario.usuario_rol === 'admin'
      || this.usuario.usuario_id === comentario.comentario_usuario_id
    );
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
      return item?._moderacion?.mensaje_usuario || 'El comentario fue enviado a revision por moderacion IA.';
    }

    if (estadoIa === 'bloqueado' || estadoModeracion === 'bloqueado') {
      return item?._moderacion?.mensaje_usuario || 'El comentario fue bloqueado por moderacion IA.';
    }

    return mensajeVisible;
  }

  private mensajeModeracionDesdeError(err: any, fallback: string): string {
    return err?.error?._moderacion?.mensaje_usuario
      || err?.error?.error
      || fallback;
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
}
