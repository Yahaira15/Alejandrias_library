import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-publicaciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminPublicaciones extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de publicaciones',
    recurso: 'publicaciones',
    idKey: 'publicacion_id',
    searchKeys: ['publicacion_titulo', 'publicacion_contenido', 'publicacion_foro_id', 'publicacion_usuario_id'],
    fields: [
      { key: 'publicacion_titulo', label: 'Titulo', required: true },
      { key: 'publicacion_foro_id', label: 'ID foro', type: 'number', required: true },
      { key: 'publicacion_usuario_id', label: 'ID usuario', type: 'number', required: true },
      { key: 'publicacion_destacada', label: 'Destacada', type: 'checkbox' },
      { key: 'publicacion_contenido', label: 'Contenido', type: 'textarea', required: true }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }
}
