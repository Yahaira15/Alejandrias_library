import { Component, OnInit } from '@angular/core';
import { PerfilService } from '../services/perfil';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
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
  passwordNueva: string = '';
  confirmarPassword: string = '';
  mostrarPassword: boolean = false;
  errorPassword = '';

  constructor(
    private perfilService: PerfilService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    this.cargarPerfil();
  }

  cargarPerfil() {
    this.perfilService.getPerfil().subscribe((res: any) => {
      this.perfil = { ...res };
      this.perfilOriginal = { ...res };
      this.cdr.detectChanges();
    });
  }

  // 🔹 activar/desactivar edición
  toggleEditar() {
    this.modoEdicion = !this.modoEdicion;

    // si cancela → restaurar datos
    if (!this.modoEdicion) {
      this.perfil = { ...this.perfilOriginal };
    }
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
    usuario_email: this.perfil.usuario_email
  };

  if (this.passwordNueva) {
    data.usuario_password = this.passwordNueva;
  }

  this.perfilService.updatePerfil(data).subscribe({
    next: () => {
      this.mensaje = 'Perfil actualizado correctamente';
      this.passwordNueva = '';
      this.confirmarPassword = '';
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