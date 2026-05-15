import { Injectable } from '@angular/core';

export interface SolicitudLiderEmail {
  nombre: string;
  apellido: string;
  apodo: string;
  email: string;
  razon: string;
  tipoContenido: string;
}

const EMAILJS_CONFIG = {
  serviceId: 'service_8iv7vfp',
  templateId: 'template_e3z74vh',
  publicKey: 'L6sNKHoQSclIiC3d7',
  destino: 'bohorquezkevin12@gmail.com'
};

@Injectable({ providedIn: 'root' })
export class EmailjsLiderService {
  enviarSolicitud(data: SolicitudLiderEmail): Promise<Response> {
    const payload = {
      service_id: EMAILJS_CONFIG.serviceId,
      template_id: EMAILJS_CONFIG.templateId,
      user_id: EMAILJS_CONFIG.publicKey,
      template_params: {
        to_email: EMAILJS_CONFIG.destino,
        usuario_nombre: data.nombre,
        usuario_apellido: data.apellido,
        usuario_apodo: data.apodo,
        usuario_email: data.email,
        razon_lider: data.razon,
        tipo_contenido: data.tipoContenido
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
        throw new Error(detalle || 'EmailJS no pudo enviar la solicitud');
      }

      return response;
    });
  }
}
