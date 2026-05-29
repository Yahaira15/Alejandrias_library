import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { EmailjsSancionService } from '../../services/emailjs-sancion.service';

@Component({
  selector: 'app-admin-reportes',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-reportes.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss', './admin-reportes.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminReportes implements OnInit, OnDestroy {
  reportes: any[] = [];
  cargando = false;
  error = '';
  mensaje = '';
  reporteSeleccionado: any = null;
  sancion = {
    sancion_usuario_id: null as number | null,
    sancion_tipo: 'advertencia',
    duracion: 'permanente',
    sancion_motivo: ''
  };
  private avisoTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private adminService: AdminService,
    private emailjsSancionService: EmailjsSancionService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  ngOnDestroy(): void {
    this.limpiarAvisoTimer();
  }

  cargar(): void {
    this.cargando = true;
    this.adminService.listar('reportes').subscribe({
      next: (res) => {
        this.reportes = Array.isArray(res) ? res : [];
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.mostrarError(err?.error?.error || 'No se pudieron cargar los reportes.');
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  seleccionarReporte(reporte: any): void {
    this.reporteSeleccionado = reporte;
    this.sancion = {
      sancion_usuario_id: this.obtenerUsuarioReportadoId(reporte),
      sancion_tipo: 'advertencia',
      duracion: 'permanente',
      sancion_motivo: reporte.reporte_motivo || ''
    };
    this.cdr.detectChanges();
  }

  actualizarEstado(reporte: any, estado: string): void {
    const payload = {
      reporte_estado: estado,
      reporte_prioridad: reporte.reporte_prioridad || 'media',
      decision_final: reporte.decision_final || '',
      riesgo: reporte.riesgo || 0,
      ia_detectado: !!reporte.ia_detectado
    };

    this.adminService.actualizar('reportes', reporte.reporte_id, payload).subscribe({
      next: () => {
        this.mostrarMensaje('Reporte actualizado.');
        this.cargar();
      },
      error: (err) => {
        this.mostrarError(err?.error?.error || 'No se pudo actualizar el reporte.');
        this.cdr.detectChanges();
      }
    });
  }

  aplicarSancion(): void {
    if (!this.reporteSeleccionado || !this.sancion.sancion_usuario_id || !this.sancion.sancion_motivo.trim()) {
      this.mostrarError('Selecciona un usuario y escribe el motivo de la sancion.');
      this.cdr.detectChanges();
      return;
    }

    this.adminService.sancionarReporte(this.reporteSeleccionado.reporte_id, this.sancion).subscribe({
      next: (sancionCreada) => {
        this.mostrarMensaje('Sancion aplicada.');

        if (this.debeEnviarCorreo(sancionCreada)) {
          this.enviarCorreoSancion(sancionCreada);
        }

        this.reporteSeleccionado = null;
        this.cargar();
      },
      error: (err) => {
        this.mostrarError(err?.error?.error || 'No se pudo aplicar la sancion.');
        this.cdr.detectChanges();
      }
    });
  }

  private debeEnviarCorreo(sancion: any): boolean {
    return ['suspension', 'ban'].includes(sancion?.sancion_tipo);
  }

  private enviarCorreoSancion(sancion: any): void {
    const usuario = sancion.usuario;

    if (!usuario?.usuario_email) {
      this.mostrarMensaje('Sancion aplicada, pero el usuario no tiene correo registrado.');
      this.cdr.detectChanges();
      return;
    }

    this.emailjsSancionService.enviarSancion({
      toEmail: usuario.usuario_email,
      usuarioNombre: `${usuario.usuario_nombre || ''} ${usuario.usuario_apellido || ''}`.trim() || usuario.usuario_apodo || 'Usuario',
      usuarioApodo: usuario.usuario_apodo || 'usuario',
      sancionTipo: this.nombreSancion(sancion.sancion_tipo),
      sancionNivel: sancion.sancion_nivel,
      sancionMotivo: sancion.sancion_motivo,
      sancionFechaInicio: this.formatearFecha(sancion.sancion_fecha_inicio),
      sancionFechaFin: sancion.sancion_fecha_fin ? this.formatearFecha(sancion.sancion_fecha_fin) : 'Permanente',
      reporteMotivo: sancion.reporte?.reporte_motivo || this.reporteSeleccionado?.reporte_motivo || 'Reporte revisado por administracion',
      decisionFinal: sancion.reporte?.decision_final || 'Sancion aplicada: ' + this.nombreSancion(sancion.sancion_tipo)
    }).then(() => {
      this.mostrarMensaje('Sancion aplicada y correo enviado.');
      this.cdr.detectChanges();
    }).catch((err) => {
      console.error('Error enviando correo de sancion:', err);
      this.mostrarError(err?.message || 'EmailJS no pudo enviar el aviso.');
      this.cdr.detectChanges();
    });
  }

  private nombreSancion(tipo: string): string {
    if (tipo === 'ban') return 'Ban permanente';
    if (tipo === 'suspension') return 'Suspension';
    if (tipo === 'restriccion') return 'Restriccion temporal';
    return 'Advertencia';
  }

  private formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) return 'No definida';

    const valor = new Date(fecha);

    if (Number.isNaN(valor.getTime())) {
      return String(fecha);
    }

    return valor.toLocaleString('es-CO', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  cancelar(): void {
    this.reporteSeleccionado = null;
    this.sancion = {
      sancion_usuario_id: null as number | null,
      sancion_tipo: 'advertencia',
      duracion: 'permanente',
      sancion_motivo: ''
    };
    this.cdr.detectChanges();
  }

  obtenerUsuarioReportadoId(reporte: any): number | null {
    if (reporte.reporte_tipo === 'usuario') return Number(reporte.reporte_referencia_id);
    if (reporte.reporte_tipo === 'publicacion') return Number(reporte.referencia?.publicacion_usuario_id);
    if (reporte.reporte_tipo === 'comentario') return Number(reporte.referencia?.comentario_usuario_id);
    if (reporte.reporte_tipo === 'foro') return Number(reporte.referencia?.foro_creador_id);
    return null;
  }

  resumenReferencia(reporte: any): string {
    const ref = reporte.referencia;
    if (!ref) return 'Referencia no disponible';
    if (reporte.reporte_tipo === 'publicacion') return ref.publicacion_titulo || 'Publicacion';
    if (reporte.reporte_tipo === 'comentario') return ref.comentario_contenido || 'Comentario';
    if (reporte.reporte_tipo === 'usuario') return ref.usuario_apodo || ref.usuario_email || 'Usuario';
    if (reporte.reporte_tipo === 'foro') return ref.foro_titulo || 'Foro';
    return 'Referencia';
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
