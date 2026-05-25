import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { RouterModule } from '@angular/router';
import { EmailjsRecuperacionService } from '../../services/emailjs-recuperacion.service';

interface LoginResponse {
  usuario: {
    usuario_intereses?: string[] | string | null;
    usuario_rol?: string;
  };
  token: string;
  mensaje?: string;
}

interface RecuperacionResponse {
  usuario_nombre: string;
  usuario_apodo: string;
  usuario_email: string;
  password_temporal: string;
  mensaje?: string;
}

@Component({
  selector: 'app-login',
  imports: [FormsModule, CommonModule, RouterModule],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {
  constructor(
    private http: HttpClient,
    private router: Router,
    private emailjsRecuperacionService: EmailjsRecuperacionService,
    private cdr: ChangeDetectorRef
  ) {}

  mostrarPassword = false;

  loginData = {
    login: '',
    usuario_password: ''
  };

  errorMensaje = '';
  cargandoLogin = false;
  modalRecuperacionAbierto = false;
  emailRecuperacion = '';
  errorRecuperacion = '';
  exitoRecuperacion = '';
  cargandoRecuperacion = false;

  iniciarSesion(form: any): void {
    this.errorMensaje = '';

    if (this.cargandoLogin) return;

    if (form.invalid) {
      this.errorMensaje = 'Completa todos los campos';
      this.cdr.detectChanges();
      return;
    }

    this.loginData.login = this.loginData.login.trim();
    this.cargandoLogin = true;
    this.cdr.detectChanges();

    this.http.post<LoginResponse>('http://localhost:8000/api/login', this.loginData)
      .subscribe({
        next: (res) => {
          this.cargandoLogin = false;
          this.cdr.detectChanges();

          localStorage.setItem('usuario', JSON.stringify(res.usuario));
          localStorage.setItem('token', res.token);

          const intereses = this.normalizarIntereses(res.usuario?.usuario_intereses);
          
          if (res.usuario?.usuario_rol === 'admin'){
            this.router.navigate(['/admin']);
          }else{
            this.router.navigate([intereses.length > 0 ? '/foros' : '/intereses']);
          }
        },
        error: (err) => {
          this.cargandoLogin = false;
          console.error(err);

          if (err.error?.mensaje) {
            this.errorMensaje = err.error.mensaje;
            this.cdr.detectChanges();
            return;
          }

          if (err.error?.message) {
            this.errorMensaje = err.error.message;
            this.cdr.detectChanges();
            return;
          }

          if (err.error?.error) {
            this.errorMensaje = err.error.error;
            this.cdr.detectChanges();
            return;
          }

          this.errorMensaje = err.status === 401
            ? 'Credenciales incorrectas'
            : 'Error en el servidor';
          this.cdr.detectChanges();
        }
      });
  }

  abrirModalRecuperacion(): void {
    this.modalRecuperacionAbierto = true;
    this.emailRecuperacion = '';
    this.errorRecuperacion = '';
    this.exitoRecuperacion = '';
  }

  cerrarModalRecuperacion(): void {
    if (this.cargandoRecuperacion) return;

    this.modalRecuperacionAbierto = false;
    this.emailRecuperacion = '';
    this.errorRecuperacion = '';
    this.exitoRecuperacion = '';
  }

  recuperarPassword(form: any): void {
    this.errorRecuperacion = '';
    this.exitoRecuperacion = '';

    if (this.cargandoRecuperacion) return;

    if (form.invalid) {
      this.errorRecuperacion = 'Ingresa un correo valido';
      this.cdr.detectChanges();
      return;
    }

    const usuarioEmail = this.emailRecuperacion.trim();
    this.cargandoRecuperacion = true;
    this.cdr.detectChanges();

    this.http.post<RecuperacionResponse>('http://localhost:8000/api/recuperar-password', { usuario_email: usuarioEmail })
      .subscribe({
        next: (res) => {
          this.emailjsRecuperacionService.enviarPasswordTemporal({
            toEmail: res.usuario_email,
            usuarioNombre: res.usuario_nombre,
            usuarioApodo: res.usuario_apodo,
            passwordTemporal: res.password_temporal
          }).then(() => {
            this.cargandoRecuperacion = false;
            this.exitoRecuperacion = 'Te enviamos una contrasena temporal. Revisa tu correo.';
            this.emailRecuperacion = '';
            this.cdr.detectChanges();
          }).catch((err) => {
            this.cargandoRecuperacion = false;
            this.errorRecuperacion = err?.message || 'No se pudo enviar el correo de recuperacion.';
            this.cdr.detectChanges();
          });
        },
        error: (err) => {
          this.cargandoRecuperacion = false;
          this.errorRecuperacion = err.error?.mensaje || err.error?.error || 'No se pudo recuperar la contrasena.';
          this.cdr.detectChanges();
        }
      });
  }

  private normalizarIntereses(intereses: string[] | string | null | undefined): string[] {
    if (Array.isArray(intereses)) return intereses;

    if (typeof intereses === 'string' && intereses.trim()) {
      try {
        const parsed = JSON.parse(intereses) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
      } catch {
        return [];
      }
    }

    return [];
  }
}
