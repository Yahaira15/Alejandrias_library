import { Injectable } from '@angular/core';

export interface SancionEmail {
  toEmail: string;
  usuarioNombre: string;
  usuarioApodo: string;
  sancionTipo: string;
  sancionNivel: number | string;
  sancionMotivo: string;
  sancionFechaInicio: string;
  sancionFechaFin: string;
  reporteMotivo: string;
  decisionFinal: string;
}

const EMAILJS_CONFIG = {
  serviceId: 'service_8iv7vfp',
  templateId: 'template_33a5uhj',
  publicKey: 'L6sNKHoQSclIiC3d7'
};

@Injectable({ providedIn: 'root' })
export class EmailjsSancionService {
  enviarSancion(data: SancionEmail): Promise<Response> {
    const payload = {
      service_id: EMAILJS_CONFIG.serviceId,
      template_id: EMAILJS_CONFIG.templateId,
      user_id: EMAILJS_CONFIG.publicKey,
      template_params: {
        to_email: data.toEmail,
        usuario_nombre: data.usuarioNombre,
        usuario_apodo: data.usuarioApodo,
        sancion_tipo: data.sancionTipo,
        sancion_nivel: data.sancionNivel,
        sancion_motivo: data.sancionMotivo,
        sancion_fecha_inicio: data.sancionFechaInicio,
        sancion_fecha_fin: data.sancionFechaFin,
        reporte_motivo: data.reporteMotivo,
        decision_final: data.decisionFinal,
        app_nombre: "Alejandria's Library"
      }
    };

    return fetch('https://api.emailjs.com/api/v1.0/email/send', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    }).then(async (response) => {
      if (!response.ok) {
        const detalle = await response.text();
        throw new Error(detalle || 'EmailJS no pudo enviar el aviso de sancion');
      }

      return response;
    });
  }
}
