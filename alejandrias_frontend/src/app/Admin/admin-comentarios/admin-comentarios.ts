import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-comentarios',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminComentarios extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de comentarios',
    recurso: 'comentarios',
    idKey: 'comentario_id',
    searchKeys: ['comentario_contenido', 'comentario_usuario_id', 'comentario_publicacion_id'],
    fields: [
      { key: 'comentario_usuario_id', label: 'ID usuario', type: 'number', required: true },
      { key: 'comentario_publicacion_id', label: 'ID publicacion', type: 'number', required: true },
      { key: 'comentario_contenido', label: 'Contenido', type: 'textarea', required: true }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }
}
