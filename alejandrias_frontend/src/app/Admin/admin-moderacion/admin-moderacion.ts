import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { AdminService } from '../../services/admin.service';

@Component({
  selector: 'app-admin-moderacion',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './admin-moderacion.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss', './admin-moderacion.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminModeracion implements OnInit {
  registros: any[] = [];
  cargando = false;
  error = '';
  mensaje = '';

  constructor(
    private adminService: AdminService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.route.queryParamMap.subscribe((params) => {
      const moderacionId = Number(params.get('moderacion_id'));

      if (moderacionId) {
        this.cargarRegistro(moderacionId);
        return;
      }

      this.cargar();
    });
  }

  cargarRegistro(id: number): void {
    this.cargando = true;
    this.error = '';

    this.adminService.obtenerModeracion(id).subscribe({
      next: (registro) => {
        this.registros = registro ? [registro] : [];
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.error = err?.error?.message || err?.error?.error || 'No se pudo cargar el registro de moderacion.';
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  cargar(): void {
    this.cargando = true;
    this.error = '';

    this.adminService.listarModeracion({ pendientes: true, limit: 100 }).subscribe({
      next: (res) => {
        this.registros = Array.isArray(res) ? res : [];
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.error = err?.error?.message || err?.error?.error || 'No se pudo cargar la cola de moderacion.';
        this.cargando = false;
        this.cdr.detectChanges();
      }
    });
  }

  aprobar(registro: any): void {
    this.adminService.aprobarModeracion(registro.moderacion_id).subscribe({
      next: () => {
        this.mensaje = 'Contenido aprobado y visible.';
        this.cargar();
      },
      error: (err) => {
        this.error = err?.error?.message || 'No se pudo aprobar el contenido.';
        this.cdr.detectChanges();
      }
    });
  }

  rechazar(registro: any): void {
    this.adminService.rechazarModeracion(registro.moderacion_id).subscribe({
      next: () => {
        this.mensaje = 'Contenido ocultado.';
        this.cargar();
      },
      error: (err) => {
        this.error = err?.error?.message || 'No se pudo ocultar el contenido.';
        this.cdr.detectChanges();
      }
    });
  }

  tipoContenido(registro: any): string {
    if (registro.comentario_id) return 'Comentario';
    if (registro.publicacion_id) return 'Publicacion';
    if (registro.foro_id) return 'Foro';
    return 'Contenido';
  }

  usuario(registro: any): string {
    const usuario = registro.usuario;
    if (!usuario) return 'Usuario desconocido';
    return usuario.usuario_apodo || usuario.usuario_email || `Usuario ${usuario.usuario_id}`;
  }

  fecha(registro: any): string {
    const valor = new Date(registro.created_at);
    return Number.isNaN(valor.getTime()) ? '' : valor.toLocaleString('es-CO');
  }

  abrirContenido(registro: any): void {
    const ruta = this.rutaContenido(registro);
    if (ruta) {
      this.router.navigateByUrl(ruta);
    }
  }

  rutaContenido(registro: any): string {
    if (registro.comentario_id && registro.comentario?.comentario_publicacion_id) {
      return `/publicaciones/${registro.comentario.comentario_publicacion_id}#comentario-${registro.comentario_id}`;
    }

    if (registro.publicacion_id) {
      return `/publicaciones/${registro.publicacion_id}`;
    }

    if (registro.foro_id) {
      return `/foros/${registro.foro_id}`;
    }

    return '';
  }
}
