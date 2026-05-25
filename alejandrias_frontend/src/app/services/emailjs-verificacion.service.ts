import { Injectable } from '@angular/core';

export interface VerificacionEmail {
  toEmail: string;
  usuarioNombre: string;
  codigoVerificacion: string;
}

const EMAILJS_CONFIG = {
  serviceId: 'service_efjnszp',
  templateId: 'template_0n96gu7',
  publicKey: 'eOtT-WFQSmE7agr5o'
};

@Injectable({ providedIn: 'root' })
export class EmailjsVerificacionService {
  enviarCodigo(data: VerificacionEmail): Promise<Response> {
    const payload = {
      service_id: EMAILJS_CONFIG.serviceId,
      template_id: EMAILJS_CONFIG.templateId,
      user_id: EMAILJS_CONFIG.publicKey,
      template_params: {
        to_email: data.toEmail,
        usuario_nombre: data.usuarioNombre,
        codigo_verificacion: data.codigoVerificacion,
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
        throw new Error(detalle || 'EmailJS no pudo enviar el codigo de verificacion');
      }

      return response;
    });
  }
}
