import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-usuarios',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminUsuarios extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de usuarios',
    recurso: 'usuarios',
    idKey: 'usuario_id',
    searchKeys: ['usuario_nombre', 'usuario_apellido', 'usuario_apodo', 'usuario_email', 'usuario_rol'],
    fields: [
      { key: 'usuario_nombre', label: 'Nombre', required: true },
      { key: 'usuario_apellido', label: 'Apellido' },
      { key: 'usuario_apodo', label: 'Apodo', required: true },
      { key: 'usuario_email', label: 'Correo', required: true },
      { key: 'usuario_rol', label: 'Rol', type: 'select', required: true, options: [
        { label: 'Explorador', value: 'explorador' },
        { label: 'Lider', value: 'lider' },
        { label: 'Administrador', value: 'admin' }
      ] },
      { key: 'usuario_bloqueado', label: 'Bloqueado', type: 'checkbox' },
      { key: 'usuario_password', label: 'Contrasena', type: 'password', hideInTable: true },
      { key: 'usuario_bio', label: 'Biografia', type: 'textarea' }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }
}
