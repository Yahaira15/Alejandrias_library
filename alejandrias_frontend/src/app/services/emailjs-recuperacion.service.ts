import { Injectable } from '@angular/core';

export interface RecuperacionPasswordEmail {
  toEmail: string;
  usuarioNombre: string;
  usuarioApodo: string;
  passwordTemporal: string;
}

const EMAILJS_CONFIG = {
  serviceId: 'service_efjnszp',
  templateId: 'template_tl5ot8l',
  publicKey: 'eOtT-WFQSmE7agr5o'
};

@Injectable({ providedIn: 'root' })
export class EmailjsRecuperacionService {
  enviarPasswordTemporal(data: RecuperacionPasswordEmail): Promise<Response> {
    const payload = {
      service_id: EMAILJS_CONFIG.serviceId,
      template_id: EMAILJS_CONFIG.templateId,
      user_id: EMAILJS_CONFIG.publicKey,
      template_params: {
        to_email: data.toEmail,
        usuario_nombre: data.usuarioNombre,
        usuario_apodo: data.usuarioApodo,
        password_temporal: data.passwordTemporal,
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
        throw new Error(detalle || 'EmailJS no pudo enviar la recuperacion de contrasena');
      }

      return response;
    });
  }
}
