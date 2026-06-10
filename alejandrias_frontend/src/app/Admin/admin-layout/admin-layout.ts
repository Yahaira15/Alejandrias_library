import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { NotificacionService } from '../../services/notificacion.service';

interface NotificacionAdmin {
  notificacion_id: number;
  notificacion_tipo: string;
  notificacion_contenido: string;
  notificacion_leida: boolean;
  notificacion_fecha: string;
  notificacion_url?: string | null;
  notificacion_referencia_id?: number | null;
}

@Component({
  selector: 'app-admin-layout',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './admin-layout.html',
  styleUrls: ['./admin-layout.scss']
})
export class AdminLayout implements OnInit, OnDestroy {
  notificaciones: NotificacionAdmin[] = [];
  cantidadNoLeidas = 0;
  mostrarPanelNotificaciones = false;
  cargandoNotificaciones = false;
  errorNotificaciones = '';
  private refrescoNotificaciones?: ReturnType<typeof setInterval>;
  esAdmin = false;

  constructor(
    private router: Router,
    private notificacionService: NotificacionService,
  ) {}

  ngOnInit(): void {
    const usuario = JSON.parse(localStorage.getItem('usuario') || 'null');
    this.esAdmin = usuario?.usuario_rol === 'admin';
    this.cargarNotificaciones();
    this.refrescoNotificaciones = setInterval(() => {
      this.cargarNotificaciones(false);
    }, 30000);
  }

  ngOnDestroy(): void {
    if (this.refrescoNotificaciones) {
      clearInterval(this.refrescoNotificaciones);
    }
  }

  cargarNotificaciones(mostrarCarga = true): void {
    if (mostrarCarga) {
      this.cargandoNotificaciones = true;
    }

    this.errorNotificaciones = '';

    this.notificacionService.getNotificaciones().subscribe({
      next: (res) => {
        this.notificaciones = Array.isArray(res) ? res : [];
        this.cantidadNoLeidas = this.notificaciones.filter(item => !item.notificacion_leida).length;
        this.cargandoNotificaciones = false;
      },
      error: () => {
        this.errorNotificaciones = 'No se pudieron cargar las notificaciones.';
        this.cargandoNotificaciones = false;
      },
    });
  }

  toggleNotificaciones(): void {
    this.mostrarPanelNotificaciones = !this.mostrarPanelNotificaciones;

    if (this.mostrarPanelNotificaciones) {
      this.cargarNotificaciones(false);
    }
  }

  marcarComoLeida(notificacion: NotificacionAdmin): void {
    if (notificacion.notificacion_leida) {
      return;
    }

    this.notificacionService.marcarLeida(notificacion.notificacion_id).subscribe({
      next: () => {
        notificacion.notificacion_leida = true;
        this.cantidadNoLeidas = Math.max(0, this.cantidadNoLeidas - 1);
      },
    });
  }

  abrirNotificacion(notificacion: NotificacionAdmin): void {
    const destino = this.notificacionService.resolverDestino(notificacion);

    if (!notificacion.notificacion_leida) {
      this.notificacionService.marcarLeida(notificacion.notificacion_id).subscribe({
        next: () => {
          notificacion.notificacion_leida = true;
          this.cantidadNoLeidas = Math.max(0, this.cantidadNoLeidas - 1);
          this.navegarNotificacion(destino);
        },
        error: () => this.navegarNotificacion(destino),
      });
      return;
    }

    this.navegarNotificacion(destino);
  }

  obtenerTiempo(fecha: string): string {
    if (!fecha) {
      return '';
    }

    return formatDistanceToNow(new Date(fecha), {
      addSuffix: true,
      locale: es,
    });
  }

  logout(): void {
    localStorage.removeItem('usuario');
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }

  private navegarNotificacion(destino: string): void {
    this.mostrarPanelNotificaciones = false;
    this.router.navigateByUrl(destino);
  }

  private rutaPorTipo(notificacion: NotificacionAdmin): string {
    if (notificacion.notificacion_tipo?.startsWith('alerta_ia_')) {
      return '/admin/moderacion';
    }

    if (notificacion.notificacion_tipo === 'nuevo_reporte') {
      return '/admin/reportes';
    }

    return '/admin/reportes';
  }
}
