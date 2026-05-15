import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-categorias',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminCategorias extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de categorias',
    recurso: 'categorias',
    idKey: 'categoria_id',
    searchKeys: ['categoria_nombre', 'categoria_descripcion'],
    fields: [
      { key: 'categoria_nombre', label: 'Nombre', required: true },
      { key: 'categoria_descripcion', label: 'Descripcion', type: 'textarea' }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }
}
