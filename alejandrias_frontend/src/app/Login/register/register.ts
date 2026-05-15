import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule, NgForm } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';

interface Interes {
  id: string;
  nombre: string;
  icono: string;
}

@Component({
  selector: 'app-register',
  imports: [FormsModule, CommonModule, RouterModule],
  templateUrl: './register.html',
  styleUrl: './register.scss'
})
export class Register implements OnInit {
  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {}

  errores: Record<string, string[]> = {};
  errorMensaje = '';
  mostrarPassword = false;
  rol = 'explorador';
  pasoActual: 1 | 2 = 1;
  cargandoRegistro = false;

  readonly intereses: Interes[] = [
    { id: 'programacion', nombre: 'Programacion', icono: 'intereses/laptop.png' },
    { id: 'matematicas', nombre: 'Matematicas', icono: 'intereses/calculadora.png' },
    { id: 'historia', nombre: 'Historia', icono: 'intereses/globo.png' },
    { id: 'literatura', nombre: 'Literatura', icono: 'intereses/tintero.png' },
    { id: 'biologia', nombre: 'Biologia', icono: 'intereses/microscopio.png' },
    { id: 'politica', nombre: 'Politica', icono: 'intereses/balanza.png' },
    { id: 'idiomas', nombre: 'Idiomas', icono: 'intereses/idiomas.png' },
    { id: 'bienestar', nombre: 'Bienestar', icono: 'intereses/bienestar.png' },
  ];

  usuario = {
    usuario_nombre: '',
    usuario_apellido: '',
    usuario_apodo: '',
    usuario_email: '',
    usuario_password: '',
    confirmPassword: '',
    usuario_rol: 'explorador',
    usuario_intereses: [] as string[],
  };

  apodoDisponible: boolean | null = null;
  apodoVerificando = false;
  private apodoConsultaId = 0;

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      if (params['rol']) {
        this.rol = params['rol'];
        this.usuario.usuario_rol = this.rol;
      }
    });
  }

  get passwordsNoCoinciden(): boolean {
    return !!this.usuario.confirmPassword &&
      this.usuario.usuario_password !== this.usuario.confirmPassword;
  }

  verificarApodo(): void {
    const apodo = this.usuario.usuario_apodo.trim();
    const consultaActual = ++this.apodoConsultaId;

    this.apodoDisponible = null;

    if (!apodo) {
      this.apodoVerificando = false;
      this.cdr.detectChanges();
      return;
    }

    this.apodoVerificando = true;
    this.cdr.detectChanges();

    this.http.get<{ disponible: boolean }>(`http://localhost:8000/api/verificar-apodo/${encodeURIComponent(apodo)}`)
      .subscribe({
        next: res => {
          if (consultaActual !== this.apodoConsultaId) return;

          this.apodoDisponible = res.disponible;
          this.apodoVerificando = false;
          this.cdr.detectChanges();
        },
        error: () => {
          if (consultaActual !== this.apodoConsultaId) return;

          this.apodoDisponible = null;
          this.apodoVerificando = false;
          this.cdr.detectChanges();
        }
      });
  }

  continuar(form: NgForm): void {
    this.errorMensaje = '';
    this.errores = {};

    if (this.cargandoRegistro) return;

    if (form.invalid) {
      this.errorMensaje = 'Corrige los errores del formulario';
      return;
    }

    if (this.passwordsNoCoinciden) {
      this.errorMensaje = 'Las contrasenas no coinciden';
      return;
    }

    if (this.apodoDisponible === false || this.apodoVerificando) {
      this.errorMensaje = 'Elige un apodo disponible antes de continuar';
      return;
    }

    this.pasoActual = 2;
  }

  toggleInteres(interesId: string): void {
    if (this.cargandoRegistro) return;

    const seleccionados = this.usuario.usuario_intereses;
    if (seleccionados.includes(interesId)) {
      this.usuario.usuario_intereses = seleccionados.filter(id => id !== interesId);
      return;
    }

    this.usuario.usuario_intereses = [...seleccionados, interesId];
  }

  interesSeleccionado(interesId: string): boolean {
    return this.usuario.usuario_intereses.includes(interesId);
  }

  registrar(): void {
    this.errorMensaje = '';
    this.errores = {};

    if (this.cargandoRegistro) return;

    if (this.usuario.usuario_intereses.length === 0) {
      this.errorMensaje = 'Selecciona al menos un interes';
      return;
    }

    this.cargandoRegistro = true;

    const body = {
      usuario_nombre: this.usuario.usuario_nombre.trim(),
      usuario_apellido: this.usuario.usuario_apellido.trim(),
      usuario_apodo: this.usuario.usuario_apodo.trim(),
      usuario_email: this.usuario.usuario_email.trim(),
      usuario_password: this.usuario.usuario_password,
      usuario_rol: this.rol,
      usuario_intereses: this.usuario.usuario_intereses,
    };

    this.http.post('http://127.0.0.1:8000/api/register', body)
      .subscribe({
        next: () => {
          this.cargandoRegistro = false;
          this.router.navigate(['/login']);
        },
        error: (err) => {
          this.cargandoRegistro = false;

          if (err.status === 422 && err.error?.errors) {
            this.errores = err.error.errors;
            this.errorMensaje = 'Revisa los datos del registro';
          } else {
            this.errorMensaje = err.error?.detalle || err.error?.error || 'No se pudo completar el registro';
          }

          this.cdr.detectChanges();
        }
      });
  }
}
