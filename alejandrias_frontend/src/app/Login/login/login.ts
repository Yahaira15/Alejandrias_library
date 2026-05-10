import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { RouterModule } from '@angular/router';

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
    private cdr: ChangeDetectorRef
  ) {}

  mostrarPassword: boolean = false;

  loginData = {
    login: '',
    usuario_password: ''
  };

  errorMensaje: string = '';
  cargandoLogin: boolean = false;

  iniciarSesion(form: any) {
    console.log('LOGIN DATA:', this.loginData);
    this.errorMensaje = '';

    if (form.invalid) {
      this.errorMensaje = 'Completa todos los campos';
      this.cdr.detectChanges();
      return;
    }

    this.loginData.login = this.loginData.login.trim();
    this.cargandoLogin = true;
    this.cdr.detectChanges();

    this.http.post<any>('http://localhost:8000/api/login', this.loginData)
      .subscribe({
        next: (res) => {
          this.cargandoLogin = false;
          this.cdr.detectChanges();
          console.log(res.token);

          localStorage.setItem('usuario', JSON.stringify(res.usuario));
          localStorage.setItem('token', res.token);
          const id = res.usuario.usuario_id;

          this.router.navigate(['/foros']);
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
}
