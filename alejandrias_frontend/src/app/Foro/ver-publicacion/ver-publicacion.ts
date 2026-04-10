import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ForoService } from '../../services/foro';

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

  constructor(
    private route: ActivatedRoute,
    private foroService: ForoService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
    this.publicacionId = Number(this.route.snapshot.paramMap.get('publicacion_id'));
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

  cargarComentarios(): void {
    this.loadingComentarios = true;

    this.foroService.getComentariosPublicacion(this.publicacionId).subscribe({
      next: (data) => {
        this.comentarios = Array.isArray(data) ? data : [];
        this.comentariosVisiblesCount = this.comentariosPorPagina;
        this.loadingComentarios = false;
        this.cdr.detectChanges();
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
        this.comentarios = [...this.comentarios, comentario];
        this.comentariosVisiblesCount = Math.max(this.comentariosVisiblesCount, this.comentarios.length);
        this.nuevoComentario = '';
        this.creandoComentario = false;
        this.mostrarFeedback('success', 'Comentario enviado correctamente.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error creando comentario:', err);
        this.creandoComentario = false;
        this.mostrarFeedback('error', 'No se pudo enviar el comentario.');
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
        this.comentarios = this.comentarios.map(comentario =>
          comentario.comentario_id === comentarioActualizado.comentario_id
            ? { ...comentario, ...comentarioActualizado }
            : comentario
        );
        this.comentarioEditando = null;
        this.guardandoComentario = false;
        this.mostrarFeedback('success', 'Comentario actualizado correctamente.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando comentario:', err);
        this.guardandoComentario = false;
        this.mostrarFeedback('error', 'No se pudo actualizar el comentario.');
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

  esComentarioPropio(comentario: any): boolean {
    return !!this.usuario && this.usuario.usuario_id === comentario.comentario_usuario_id;
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
