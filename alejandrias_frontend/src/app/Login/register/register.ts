import { ChangeDetectorRef, Component } from '@angular/core';
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
  styleUrl: './register.scss'
})
export class Register implements OnInit{
  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {}

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
  apodoVerificando: boolean = false;
  private apodoConsultaId = 0;

  get passwordsNoCoinciden(): boolean {
    return !!this.usuario.confirmPassword &&
      this.usuario.usuario_password !== this.usuario.confirmPassword;
  }

  verificarApodo() {
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

    this.http.get<any>(`http://localhost:8000/api/verificar-apodo/${encodeURIComponent(apodo)}`)
      .subscribe(res => {
        if (consultaActual !== this.apodoConsultaId) return;

        this.apodoDisponible = res.disponible;
        this.apodoVerificando = false;
        this.cdr.detectChanges();
      }, () => {
        if (consultaActual !== this.apodoConsultaId) return;

        this.apodoDisponible = null;
        this.apodoVerificando = false;
        this.cdr.detectChanges();
      });
  }

  registrar(form: any) {
    if (form.invalid) {
    alert('Corrige los errores del formulario');
    return;
  }


    if (this.passwordsNoCoinciden) {
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
        this.cdr.detectChanges();
      }
    }
  });
  }

}
