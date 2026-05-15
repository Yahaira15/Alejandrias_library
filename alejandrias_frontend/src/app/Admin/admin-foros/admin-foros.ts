import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-foros',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminForos extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de foros',
    recurso: 'foros',
    idKey: 'foro_id',
    searchKeys: ['foro_titulo', 'foro_descripcion', 'foro_categoria_id', 'foro_creador_id'],
    fields: [
      { key: 'foro_titulo', label: 'Titulo', required: true },
      { key: 'foro_categoria_id', label: 'ID categoria', type: 'number', required: true },
      { key: 'foro_creador_id', label: 'ID creador', type: 'number', required: true },
      { key: 'foro_privado', label: 'Privado', type: 'checkbox' },
      { key: 'foro_password', label: 'Contrasena privada', type: 'password', hideInTable: true },
      { key: 'foro_descripcion', label: 'Descripcion', type: 'textarea', required: true }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }
}
