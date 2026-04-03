import { ChangeDetectionStrategy, Component } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { OnInit } from '@angular/core';

@Component({
  selector: 'app-register',
  imports: [FormsModule, CommonModule],
  templateUrl: './register.html',
  styleUrl: './register.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class Register implements OnInit{
  constructor(private http: HttpClient, private router: Router, private route: ActivatedRoute) {}

  ngOnInit() {
  this.route.queryParams.subscribe(params => {
    if (params['rol']) {
      this.rol = params['rol'];
    }
  });
}

  errores: any = {};
  mostrarPassword: boolean = false;
  rol: string = 'explorador';

  usuario = {
    usuario_nombre: '',
    usuario_apellido: '',
    usuario_apodo: '',
    usuario_email: '',
    usuario_password: '',
    confirmPassword: '',
    usuario_rol: 'explorador'
  };

  apodoDisponible: boolean | null = null;

  verificarApodo() {
    if (!this.usuario.usuario_apodo) return;

    this.http.get<any>(`http://localhost:8000/api/verificar-apodo/${this.usuario.usuario_apodo}`)
      .subscribe(res => {
        this.apodoDisponible = res.disponible;
      });
  }

  registrar(form: any) {
    if (form.invalid) {
    alert('Corrige los errores del formulario');
    return;
  }


    if (this.usuario.usuario_password !== this.usuario.confirmPassword) {
      alert('Las contraseñas no coinciden');
      return;
    }

    const body = {
      usuario_nombre: this.usuario.usuario_nombre,
      usuario_apellido: this.usuario.usuario_apellido,
      usuario_apodo: this.usuario.usuario_apodo,
      usuario_email: this.usuario.usuario_email,
      usuario_password: this.usuario.usuario_password,
      usuario_rol: this.rol
    };

    this.http.post('http://127.0.0.1:8000/api/register', body)
  .subscribe({
    next: () => {
      alert('Registro exitoso, redirigiendo a inicio de sesión...');
      setTimeout(() => {
      this.router.navigate(['/login']);
    }, 1000);
    },
    error: (err) => {
      console.error(err);

      if (err.status === 422) {
        this.errores = err.error.errors;
      }
    }
  });
  }

}
