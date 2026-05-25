import { ChangeDetectionStrategy, ChangeDetectorRef, Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../services/admin.service';
import { AdminCrudBase, AdminCrudConfig } from '../admin-crud/admin-crud-base';

@Component({
  selector: 'app-admin-sanciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: '../admin-crud/admin-crud.html',
  styleUrls: ['../admin-crud/admin-crud.shared.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AdminSanciones extends AdminCrudBase {
  config: AdminCrudConfig = {
    titulo: 'Gestion de sanciones',
    recurso: 'sanciones',
    idKey: 'sancion_id',
    searchKeys: ['sancion_usuario_id', 'sancion_tipo', 'sancion_motivo'],
    fields: [
      { key: 'sancion_usuario_id', label: 'ID usuario', type: 'number', required: true },
      { key: 'sancion_tipo', label: 'Tipo', type: 'select', required: true, options: [
        { label: 'Advertencia', value: 'advertencia' },
        { label: 'Restriccion temporal', value: 'restriccion' },
        { label: 'Suspension', value: 'suspension' },
        { label: 'Ban permanente', value: 'ban' }
      ] },
      { key: 'sancion_nivel', label: 'Nivel', type: 'number' },
      { key: 'sancion_reporte_id', label: 'ID reporte', type: 'number' },
      { key: 'sancion_fecha_fin', label: 'Fin', type: 'text', placeholder: 'YYYY-MM-DD HH:mm:ss' },
      { key: 'sancion_activa', label: 'Activa', type: 'checkbox' },
      { key: 'bloquea_comentar', label: 'Bloquea comentar', type: 'checkbox' },
      { key: 'bloquea_publicar', label: 'Bloquea publicar', type: 'checkbox' },
      { key: 'bloquea_login', label: 'Bloquea login', type: 'checkbox' },
      { key: 'sancion_motivo', label: 'Motivo', type: 'textarea', required: true }
    ]
  };

  constructor(adminService: AdminService, cdr: ChangeDetectorRef) {
    super(adminService, cdr);
  }

  protected override prepararPayload(payload: any): any {
    for (const key of ['sancion_nivel', 'sancion_reporte_id', 'sancion_fecha_fin']) {
      if (payload[key] === '' || payload[key] === null || payload[key] === undefined) {
        delete payload[key];
      }
    }

    if (!payload.sancion_nivel) {
      payload.sancion_nivel = this.nivelPorTipo(payload.sancion_tipo);
    }

    return payload;
  }

  private nivelPorTipo(tipo: string): number {
    if (tipo === 'restriccion') return 2;
    if (tipo === 'suspension') return 3;
    if (tipo === 'ban') return 4;
    return 1;
  }
}
