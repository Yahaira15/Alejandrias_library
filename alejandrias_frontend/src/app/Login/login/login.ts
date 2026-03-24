import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';

@Component({
  selector: 'app-login',
  imports: [FormsModule, CommonModule],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {

  constructor(private http: HttpClient, private router: Router) {}

  mostrarPassword: boolean = false;

  loginData = {
    login: '',
    usuario_password: ''
  };

  errorMensaje: string = '';

  iniciarSesion(form: any) {
    console.log('LOGIN DATA:', this.loginData);

    if (form.invalid) {
      this.errorMensaje = 'Completa todos los campos';
      return;
    }

    this.loginData.login = this.loginData.login.trim();

    this.http.post<any>('http://localhost:8000/api/login', this.loginData)
      .subscribe({
        next: (res) => {
          console.log(res);

          localStorage.setItem('usuario', JSON.stringify(res.usuario));

          this.router.navigate(['/foros']);
        },
        error: (err) => {
        console.error(err);

        if (err.error && err.error.mensaje) {
          this.errorMensaje = err.error.mensaje;
        } else {
          this.errorMensaje = 'Error en el servidor';
        }
      }
      });
  }
}