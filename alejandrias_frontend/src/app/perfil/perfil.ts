import { Component, OnInit } from '@angular/core';
import { PerfilService } from '../services/perfil';
import { GamificacionService, MisionUsuario, RachaUsuario, RankingUsuario } from '../services/gamificacion.service';
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
  private readonly fotoPerfilMaxBytes = 2 * 1024 * 1024;

  perfil: any = {};
  perfilOriginal: any = {}; // para cancelar cambios
  modoEdicion: boolean = false;
  guardandoPerfil: boolean = false;
  mensaje: string = '';
  mensajeTipo: 'success' | 'error' | '' = '';
  private mensajeTimeout: ReturnType<typeof setTimeout> | null = null;
  seccionActiva: 'perfil' | 'logros' = 'perfil';
  modalSolicitudLider = false;
  logros: any = null;
  racha: RachaUsuario | null = null;
  misiones: MisionUsuario[] = [];
  rankingGlobal: RankingUsuario[] = [];
  rankingSemanal: RankingUsuario[] = [];
  recompensaRachaVisible = false;
  recompensaMensaje = '';
  cargandoLogros = false;
  reclamandoRacha = false;
  reclamandoMisiones = new Set<number>();
  rankingTab: 'global' | 'semanal' = 'global';
  rutaLogrosActiva: 'lider' | 'explorador' = 'explorador';
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
  materiasFavoritas: string[] = [];
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

  // Mapeo de intereses a nombres mostrados
  interesesMap: Record<string, string> = {
    'programacion': 'Programacion',
    'matematicas': 'Matematicas',
    'historia': 'Historia',
    'literatura': 'Literatura',
    'biologia': 'Biologia',
    'politica': 'Politica',
    'idiomas': 'Idiomas',
    'bienestar': 'Bienestar'
  };

  constructor(
    private perfilService: PerfilService,
    private gamificacionService: GamificacionService,
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
      this.cargarLogros();
      this.cdr.detectChanges();
    });
  }

  cargarLogros() {
    this.cargandoLogros = true;

    this.gamificacionService.getPanel().subscribe({
      next: (res: any) => {
        this.logros = res;
        this.racha = res?.racha || null;
        this.misiones = res?.misiones || [];
        this.rankingGlobal = res?.ranking?.global_xp || [];
        this.rankingSemanal = res?.ranking?.semanal_xp || [];
        this.recompensaRachaVisible = !!this.racha && !this.racha.recompensa_reclamada;
        this.rutaLogrosActiva = res?.ruta_principal === 'lider' ? 'lider' : 'explorador';
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      }
    });
  }

  sincronizarLogros() {
    this.cargandoLogros = true;

    this.gamificacionService.getPanel().subscribe({
      next: (res: any) => {
        this.logros = res;
        this.racha = res?.racha || null;
        this.misiones = res?.misiones || [];
        this.rankingGlobal = res?.ranking?.global_xp || [];
        this.rankingSemanal = res?.ranking?.semanal_xp || [];
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      }
    });
  }

  cargarLogrosDemo() {
    this.cargandoLogros = true;

    this.perfilService.cargarLogrosDemo().subscribe({
      next: (res: any) => {
        this.logros = res;
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.cargandoLogros = false;
        this.cdr.detectChanges();
      }
    });
  }

  catalogoPorRuta(ruta: 'lider' | 'explorador') {
    return (this.logros?.catalogo || []).filter((insignia: any) => insignia.ruta === ruta);
  }

  logrosCompletados(ruta: 'lider' | 'explorador') {
    return this.catalogoPorRuta(ruta).filter((insignia: any) => insignia.obtenida);
  }

  logrosPendientes(ruta: 'lider' | 'explorador') {
    return this.catalogoPorRuta(ruta).filter((insignia: any) => !insignia.obtenida);
  }

  get xpTotal(): number {
    return Number(this.logros?.xp_total || 0);
  }

  get nivelRutaActiva(): number {
    return Number(this.logros?.nivel_actual ?? 0);
  }

  get siguienteMetaXp(): number {
    return Number(this.logros?.xp_siguiente_nivel ?? this.metaXpPorNivel(this.nivelRutaActiva + 1));
  }

  get progresoXp(): number {
    const inicioNivel = Number(this.logros?.xp_nivel_actual ?? this.metaXpPorNivel(this.nivelRutaActiva));
    const finNivel = this.siguienteMetaXp;

    if (finNivel <= inicioNivel) return 100;

    return Math.min(100, Math.max(0, Math.round(((this.xpTotal - inicioNivel) / (finNivel - inicioNivel)) * 100)));
  }

  get misionesCompletadas(): number {
    return this.misiones.filter((mision) => mision.completada).length;
  }

  get rankingActivo(): RankingUsuario[] {
    return this.rankingTab === 'global' ? this.rankingGlobal : this.rankingSemanal;
  }

  progresoMision(mision: MisionUsuario): number {
    if (!mision.objetivo) return 0;
    return Math.min(100, Math.round((mision.progreso / mision.objetivo) * 100));
  }

  misionReclamandose(mision: MisionUsuario): boolean {
    return this.reclamandoMisiones.has(mision.usuario_mision_id);
  }

  medallaIcono(medalla: RankingUsuario['medalla']): string {
    if (medalla === 'oro') return '🥇';
    if (medalla === 'plata') return '🥈';
    if (medalla === 'bronce') return '🥉';
    return '🏅';
  }

  reclamarRacha() {
    if (this.reclamandoRacha || !this.racha || this.racha.recompensa_reclamada) return;

    this.reclamandoRacha = true;
    this.gamificacionService.reclamarRacha().subscribe({
      next: (res: any) => {
        this.racha = res?.racha || this.racha;
        this.logros = res?.progreso ? { ...this.logros, ...res.progreso } : this.logros;
        this.recompensaMensaje = `+${this.racha?.xp_obtenida_hoy || 0} XP por tu racha diaria`;
        this.recompensaRachaVisible = true;
        this.reclamandoRacha = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.reclamandoRacha = false;
        this.cdr.detectChanges();
      }
    });
  }

  reclamarMision(mision: MisionUsuario) {
    if (!mision.completada || mision.reclamada || this.misionReclamandose(mision)) return;

    this.reclamandoMisiones.add(mision.usuario_mision_id);
    this.gamificacionService.reclamarMision(mision.usuario_mision_id).subscribe({
      next: (res: any) => {
        this.misiones = res?.misiones || this.misiones;
        this.logros = res?.progreso ? { ...this.logros, ...res.progreso } : this.logros;
        this.recompensaMensaje = `Mision reclamada: +${mision.xp_recompensa} XP`;
        this.recompensaRachaVisible = true;
        this.reclamandoMisiones.delete(mision.usuario_mision_id);
        this.cdr.detectChanges();
      },
      error: () => {
        this.reclamandoMisiones.delete(mision.usuario_mision_id);
        this.cdr.detectChanges();
      }
    });
  }

  private metaXpPorNivel(nivel: number): number {
    const escala = [0, 120, 280, 500, 760, 1050, 1350, 1700, 2200];
    return escala[Math.min(Math.max(nivel, 0), escala.length - 1)] || 2200;
  }

  cerrarRecompensa() {
    this.recompensaRachaVisible = false;
    this.recompensaMensaje = '';
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
    // Cargar intereses del perfil
    if (this.perfil.usuario_intereses && Array.isArray(this.perfil.usuario_intereses)) {
      this.materiasFavoritas = this.perfil.usuario_intereses
        .map((interes: string) => this.interesesMap[interes] || interes)
        .filter((materia: string) => materia); // Filtrar valores vacíos
    }

    // Si no hay intereses en el perfil, intentar cargar del localStorage (compatibilidad)
    if (this.materiasFavoritas.length === 0) {
      const materiasGuardadas = localStorage.getItem(this.obtenerClaveMaterias());
      if (materiasGuardadas) {
        this.materiasFavoritas = JSON.parse(materiasGuardadas);
      }
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

    if (archivo.size > this.fotoPerfilMaxBytes) {
      this.mostrarAviso('error', 'La foto de perfil no puede superar los 2 MB.');
      input.value = '';
      this.cdr.detectChanges();
      return;
    }

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
    if (this.seccionActiva === 'logros') {
      this.seccionActiva = 'perfil';
      return;
    }

    this.location.back();
  }

  irAMisForos() {
    this.router.navigate(['/mis-foros']);
  }

  irAAdmin() {
    this.router.navigate(['/admin']);
  }

  irALogros() {
    this.seccionActiva = 'logros';
    this.modoEdicion = false;
    if (!this.logros) {
      this.cargarLogros();
    }
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

  if (this.guardandoPerfil) return;

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
    usuario_nombre: this.perfil.usuario_nombre?.trim(),
    usuario_apellido: this.perfil.usuario_apellido?.trim() || null,
    usuario_apodo: this.perfil.usuario_apodo?.trim(),
    usuario_email: this.perfil.usuario_email?.trim(),
    usuario_bio: this.perfil.usuario_bio,
    usuario_foto_perfil: this.perfil.usuario_foto_perfil,
    usuario_intereses: this.materiasFavoritas
      .map((materia) => this.interesDesdeMateria(materia))
      .filter((interes): interes is string => !!interes)
  };

  if (this.passwordNueva) {
    data.usuario_password = this.passwordNueva;
  }

  this.guardandoPerfil = true;
  this.cdr.detectChanges();

  this.perfilService.updatePerfil(data).subscribe({
    next: (res: any) => {
      if (!res?.usuario) {
        this.mostrarAviso('error', 'Error: respuesta del servidor incompleta');
        this.guardandoPerfil = false;
        this.cdr.detectChanges();
        return;
      }

      // Actualizar perfil completamente desde la respuesta
      this.perfil = { ...res.usuario };
      this.cargarMateriasGuardadas();

      this.mostrarAviso('success', 'Perfil actualizado correctamente');
      this.passwordNueva = '';
      this.confirmarPassword = '';
      this.perfilOriginal = { ...this.perfil };
      this.materiasOriginal = [...this.materiasFavoritas];
      localStorage.setItem(this.obtenerClaveMaterias(), JSON.stringify(this.materiasFavoritas));
      localStorage.setItem('usuario', JSON.stringify(this.perfil));
      this.modoEdicion = false;
      this.guardandoPerfil = false;
      this.sincronizarLogros();
      this.cdr.detectChanges();
    },
    error: (err) => {
      this.mostrarAviso('error', this.mensajeErrorPerfil(err));
      this.guardandoPerfil = false;
      this.cdr.detectChanges();
    }
  });

}

  cerrarAviso(): void {
    this.mensaje = '';
    this.mensajeTipo = '';

    if (this.mensajeTimeout) {
      clearTimeout(this.mensajeTimeout);
      this.mensajeTimeout = null;
    }
  }

  private mostrarAviso(tipo: 'success' | 'error', mensaje: string): void {
    this.mensajeTipo = tipo;
    this.mensaje = mensaje;

    if (this.mensajeTimeout) {
      clearTimeout(this.mensajeTimeout);
    }

    this.mensajeTimeout = setTimeout(() => {
      this.mensaje = '';
      this.mensajeTipo = '';
      this.mensajeTimeout = null;
      this.cdr.detectChanges();
    }, 6500);

    this.cdr.detectChanges();
  }

  private interesDesdeMateria(materia: string): string | null {
    const normalizada = this.normalizarTexto(materia);
    const encontrado = Object.entries(this.interesesMap)
      .find(([, nombre]) => this.normalizarTexto(nombre) === normalizada);

    return encontrado?.[0] || null;
  }

  private normalizarTexto(valor: string): string {
    return valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
  }

  private mensajeErrorPerfil(err: any): string {
    const errores = err?.error?.errors;

    if (errores && typeof errores === 'object') {
      const primerError = Object.values(errores).flat()[0];
      if (primerError) {
        return String(primerError);
      }
    }

    return err?.error?.message || err?.error?.error || err?.error?.detalle || 'Error al actualizar';
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
