import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit , OnDestroy} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ForoService } from '../../services/foro';
import { NotificacionService } from '../../services/notificacion.service';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';

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
  foroSeleccionado: any = null;
  modalForoAbierto = false;
  modalPrivadoAbierto = false;
  passwordPrivado = '';
  errorPrivado = '';
  registrandoForo = false;
  buscandoPrivado = false;

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
        console.error('Error contando publicaciones de mis foros:', err);
        this.foros = foros;
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  irAInicio(): void {
    this.router.navigate(['/foros']);
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
    const confirmar = confirm('¿Estas seguro de que quieres eliminar este foro?');
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
