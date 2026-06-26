import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, ElementRef, ViewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ChatIaService, ChatMensaje } from '../services/chat-ia';

@Component({
  selector: 'app-chat-ia',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './chat-ia.html',
  styleUrl: './chat-ia.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatIaComponent {
  @ViewChild('mensajesContainer') private mensajesContainer?: ElementRef<HTMLElement>;

  mensajes: ChatMensaje[] = [
    {
      rol: 'asistente',
      texto: 'Hola. Soy el asistente de Alejandrias Library. Puedes escribirme cuando quieras.',
      fecha: new Date().toISOString(),
    }
  ];

  mensajeActual = '';
  cargando = false;
  error = '';

  constructor(
    private chatIaService: ChatIaService,
    private cdr: ChangeDetectorRef,
  ) {}

  enviarMensaje(): void {
    const texto = this.mensajeActual.trim();
    if (!texto || this.cargando) {
      return;
    }

    this.error = '';

    const mensajeUsuario: ChatMensaje = {
      rol: 'usuario',
      texto,
      fecha: new Date().toISOString(),
    };

    this.mensajes = [...this.mensajes, mensajeUsuario];
    this.mensajeActual = '';
    this.cargando = true;
    this.cdr.detectChanges();
    this.scrollAlUltimoMensaje();

    this.chatIaService.enviarMensaje(texto, this.mensajes).subscribe({
      next: (respuesta) => {
        this.error = '';

        this.mensajes = [
          ...this.mensajes,
          {
            rol: 'asistente',
            texto: respuesta?.data?.mensaje || 'No recibi una respuesta valida.',
            recomendaciones: respuesta?.data?.recomendaciones || [],
            fecha: new Date().toISOString(),
          }
        ];
        this.cargando = false;
        this.cdr.detectChanges();
        this.scrollAlUltimoMensaje();
      },
      error: () => {
        this.error = 'No se pudo obtener respuesta de la IA.';
        this.cargando = false;
        this.cdr.detectChanges();
        this.scrollAlUltimoMensaje();
      }
    });
  }

  limpiarChat(): void {
    this.mensajes = [
      {
        rol: 'asistente',
        texto: 'Chat reiniciado. Puedes hacerme una nueva pregunta.',
        fecha: new Date().toISOString(),
      }
    ];
    this.error = '';
    this.mensajeActual = '';
    this.cdr.detectChanges();
    this.scrollAlUltimoMensaje();
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.enviarMensaje();
    }
  }

  trackByIndex(index: number): number {
    return index;
  }

  private scrollAlUltimoMensaje(): void {
    requestAnimationFrame(() => {
      const contenedor = this.mensajesContainer?.nativeElement;
      if (!contenedor) {
        return;
      }

      contenedor.scrollTo({
        top: contenedor.scrollHeight,
        behavior: 'smooth',
      });
    });
  }
}
