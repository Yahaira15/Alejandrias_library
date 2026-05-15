import { Component, OnInit } from '@angular/core';
import { PerfilService } from '../services/perfil';
import { EmailjsLiderService } from '../services/emailjs-lider.service';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CommonModule, Location } from '@angular/common';
import { ChangeDetectorRef } from '@angular/core';

@Component({
  selector: 'app-perfil',
  templateUrl: './perfil.html',
  imports: [FormsModule, CommonModule],
  styleUrls: ['./perfil.scss']
})
export class Perfil implements OnInit {

  perfil: any = {};
  perfilOriginal: any = {}; // para cancelar cambios
  modoEdicion: boolean = false;
  mensaje: string = '';
  modalSolicitudLider = false;
  enviandoSolicitudLider = false;
  solicitudLiderMensaje = '';
  solicitudLiderError = '';
  solicitudLider = {
    razon: '',
    tipoContenido: ''
  };
  passwordNueva: string = '';
  confirmarPassword: string = '';
  mostrarPassword: boolean = false;
  errorPassword = '';
  materiasFavoritas: string[] = ['Programacion', 'Matematicas', 'Biologia'];
  materiasOriginal: string[] = [];
  materiasDisponibles: string[] = [
    'Programacion',
    'Matematicas',
    'Biologia',
    'Fisica',
    'Quimica',
    'Historia',
    'Literatura',
    'Filosofia',
    'Inteligencia Artificial',
    'Ciberseguridad'
  ];

  constructor(
    private perfilService: PerfilService,
    private emailjsLiderService: EmailjsLiderService,
    private router: Router,
    private location: Location,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    this.cargarPerfilLocal();
    this.cargarPerfil();
  }

  get nombreUsuarioPerfil(): string {
    return this.perfil.usuario_nombre || this.perfil.usuario_apodo || this.perfil.usuario_email || '';
  }

  cargarPerfilLocal() {
    const usuarioGuardado = localStorage.getItem('usuario');

    if (!usuarioGuardado) return;

    try {
      this.perfil = { ...JSON.parse(usuarioGuardado), ...this.perfil };
      this.perfilOriginal = { ...this.perfil };
      this.cargarMateriasGuardadas();
    } catch {
      localStorage.removeItem('usuario');
    }
  }

  cargarPerfil() {
    this.perfilService.getPerfil().subscribe((res: any) => {
      this.perfil = { ...this.perfil, ...res };
      this.perfilOriginal = { ...this.perfil };
      this.cargarMateriasGuardadas();
      this.cdr.detectChanges();
    });
  }

  // 🔹 activar/desactivar edición
  toggleEditar() {
    this.modoEdicion = !this.modoEdicion;

    // si cancela → restaurar datos
    if (!this.modoEdicion) {
      this.perfil = { ...this.perfilOriginal };
      this.materiasFavoritas = [...this.materiasOriginal];
    }
  }

  cargarMateriasGuardadas() {
    const materiasGuardadas = localStorage.getItem(this.obtenerClaveMaterias());

    if (materiasGuardadas) {
      this.materiasFavoritas = JSON.parse(materiasGuardadas);
    }

    this.materiasOriginal = [...this.materiasFavoritas];
  }

  obtenerClaveMaterias(): string {
    return `materiasFavoritas_${this.perfil.usuario_id || this.perfil.usuario_email || 'usuario'}`;
  }

  cambiarFotoPerfil(event: Event) {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) return;

    const lector = new FileReader();
    lector.onload = () => {
      this.perfil.usuario_foto_perfil = lector.result as string;
      this.cdr.detectChanges();
    };
    lector.readAsDataURL(archivo);
  }

  toggleMateria(materia: string) {
    if (this.materiaSeleccionada(materia)) {
      this.materiasFavoritas = this.materiasFavoritas.filter((item) => item !== materia);
      return;
    }

    this.materiasFavoritas = [...this.materiasFavoritas, materia];
  }

  materiaSeleccionada(materia: string): boolean {
    return this.materiasFavoritas.includes(materia);
  }

  volverAtras() {
    this.location.back();
  }

  irAMisForos() {
    this.router.navigate(['/mis-foros']);
  }

  irAAdmin() {
    this.router.navigate(['/admin']);
  }

  abrirSolicitudLider() {
    this.modalSolicitudLider = true;
    this.solicitudLiderMensaje = '';
    this.solicitudLiderError = '';
  }

  cerrarSolicitudLider() {
    if (this.enviandoSolicitudLider) return;

    this.modalSolicitudLider = false;
    this.solicitudLider = {
      razon: '',
      tipoContenido: ''
    };
    this.solicitudLiderError = '';
  }

  enviarSolicitudLider() {
    this.solicitudLiderError = '';
    this.solicitudLiderMensaje = '';

    if (!this.solicitudLider.razon.trim() || !this.solicitudLider.tipoContenido.trim()) {
      this.solicitudLiderError = 'Completa la razon y el tipo de contenido que deseas compartir.';
      return;
    }

    this.enviandoSolicitudLider = true;

    this.emailjsLiderService.enviarSolicitud({
      nombre: this.perfil.usuario_nombre || '',
      apellido: this.perfil.usuario_apellido || '',
      apodo: this.perfil.usuario_apodo || '',
      email: this.perfil.usuario_email || '',
      razon: this.solicitudLider.razon,
      tipoContenido: this.solicitudLider.tipoContenido
    }).then(() => {
      this.enviandoSolicitudLider = false;
      this.solicitudLiderMensaje = 'Solicitud enviada correctamente. Un administrador revisara tu informacion.';
      this.solicitudLider = {
        razon: '',
        tipoContenido: ''
      };
      this.cdr.detectChanges();
    }).catch((err) => {
      this.enviandoSolicitudLider = false;
      this.solicitudLiderError = `No se pudo enviar la solicitud: ${err?.message || 'revisa la configuracion de EmailJS.'}`;
      this.cdr.detectChanges();
    });
  }

  validarPassword(password: string): string | null {

  const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.#_-])[A-Za-z\d@$!%*?&.#_-]{8,}$/;

  if (!regex.test(password)) {
    return 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial';
  }

  return null;
}

actualizar() {

  this.errorPassword = '';

  // 🔐 Validación
  if (this.passwordNueva || this.confirmarPassword) {

    const error = this.validarPassword(this.passwordNueva);

    if (error) {
      this.errorPassword = error;
      return;
    }

    if (this.passwordNueva !== this.confirmarPassword) {
      this.errorPassword = 'Las contraseñas no coinciden';
      return;
    }
  }

  const data: any = {
    usuario_nombre: this.perfil.usuario_nombre,
    usuario_apodo: this.perfil.usuario_apodo,
    usuario_email: this.perfil.usuario_email,
    usuario_bio: this.perfil.usuario_bio,
    usuario_foto_perfil: this.perfil.usuario_foto_perfil
  };

  if (this.passwordNueva) {
    data.usuario_password = this.passwordNueva;
  }

  this.perfilService.updatePerfil(data).subscribe({
    next: () => {
      this.mensaje = 'Perfil actualizado correctamente';
      this.passwordNueva = '';
      this.confirmarPassword = '';
      this.perfilOriginal = { ...this.perfil };
      this.materiasOriginal = [...this.materiasFavoritas];
      localStorage.setItem(this.obtenerClaveMaterias(), JSON.stringify(this.materiasFavoritas));
      localStorage.setItem('usuario', JSON.stringify(this.perfil));
    },
    error: () => {
      this.mensaje = 'Error al actualizar';
    }
  });

  this.modoEdicion = false;
}

  // 🔹 eliminar cuenta
  eliminarCuenta() {
    if (!confirm('¿Seguro que quieres eliminar tu cuenta?')) return;

    this.perfilService.deleteCuenta().subscribe(() => {
      localStorage.clear();
      this.router.navigate(['/login']);
    });
  }

  logout() {
    localStorage.removeItem('usuario');
    localStorage.removeItem('token'); 
    this.router.navigate(['/login']);
  }
}
