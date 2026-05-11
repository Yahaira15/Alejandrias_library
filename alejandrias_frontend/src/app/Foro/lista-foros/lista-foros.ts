import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ForoService } from '../../services/foro';
import { NotificacionService } from '../../services/notificacion.service';
import { Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
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
  cargando = false;
  usuario: any;
  rol = '';

  notificaciones: any[] = [];
  mostrarPanelNotificaciones = false;
  cantidadNoLeidas = 0;
  intervaloNotificaciones: any;

  apodoUsuario = '';
  foroSeleccionado: any = null;
  modalForoAbierto = false;
  modalPrivadoAbierto = false;
  passwordPrivado = '';
  errorPrivado = '';
  errorRegistro = '';
  registrandoForo = false;
  buscandoPrivado = false;

  constructor(
    private foroService: ForoService,
    private router: Router,
    private cdr: ChangeDetectorRef,
    private notificacionService: NotificacionService
  ) {}

  ngOnInit(): void {
    this.usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
    this.rol = this.usuario.usuario_rol;
    this.apodoUsuario = this.usuario.usuario_apodo || this.usuario.apodoUsuario || this.usuario.usuario_nombre || '';
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

    this.foroService.getForosPublicos().subscribe({
      next: (res: any) => this.enriquecerForos(res ?? []),
      error: (err) => {
        console.error('ERROR FOROS PUBLICOS:', err);
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

    const solicitudes = foros.map(foro => this.foroService.getPublicaciones(foro.foro_id));

    forkJoin(solicitudes).subscribe({
      next: (resultados) => {
        this.foros = foros.map((foro, index) => {
          const publicaciones = Array.isArray(resultados[index]) ? resultados[index] : [];
          const comentariosCount = publicaciones.reduce(
            (total: number, publicacion: any) => total + (publicacion.comentarios_count || 0),
            0
          );

          return {
            ...foro,
            publicaciones_count: publicaciones.length,
            comentarios_count_total: comentariosCount
          };
        });
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error contando comentarios por foro:', err);
        this.foros = foros;
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  irACrearForo(): void {
    this.router.navigate(['/foros/crear']);
  }

  irAInicio(): void {
    this.router.navigate(['/foros']);
  }

  irAMisForos(): void {
    this.router.navigate(['/mis-foros']);
  }

  irAChatIa(): void {
    this.router.navigate(['/chat-ia']);
  }

  editarForo(id: number): void {
    this.router.navigate(['/foros/editar', id]);
  }

  abrirModalForo(foro: any): void {
    if (foro?.foro_privado && this.rol === 'lider') {
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
            notificacion.notificacion_url
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

      default:

        console.warn(
          'Tipo de notificación desconocido'
        );
    }

    this.mostrarPanelNotificaciones = false;
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
