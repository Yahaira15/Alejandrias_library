<?php

namespace App\Services\IA\Moderation;

use App\Models\ModeracionIa;
use App\Models\Usuario;
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ContentModerationService
{
    public function __construct(private IaModerationClient $client)
    {
    }

    public function analyze(array $payload): array
    {
        return $this->withUserModerationMessage(
            $this->withLocalSafetyAlert($this->client->analyze($payload), $payload),
            $payload
        );
    }

    public function applyToModel(Model $model, array $analysis, string $prefix): void
    {
        if (Schema::hasColumn($model->getTable(), 'estado_moderacion')) {
            $model->estado_moderacion = $this->visibilityFor($analysis['estado']);
        }

        if (Schema::hasColumn($model->getTable(), 'ia_riesgo')) {
            $model->ia_riesgo = $analysis['riesgo'];
        }

        if (Schema::hasColumn($model->getTable(), 'ia_razon')) {
            $model->ia_razon = $analysis['razon'];
        }

        if (Schema::hasColumn($model->getTable(), 'ia_fecha_analisis')) {
            $model->ia_fecha_analisis = now();
        }

        $legacyEstadoColumn = $prefix . '_estado_moderacion';
        $legacyVisibilidadColumn = $prefix . '_visibilidad';

        if (Schema::hasColumn($model->getTable(), $legacyEstadoColumn)) {
            $model->{$legacyEstadoColumn} = $analysis['estado'];
        }

        if (Schema::hasColumn($model->getTable(), $legacyVisibilidadColumn)) {
            $model->{$legacyVisibilidadColumn} = $this->visibilityFor($analysis['estado']);
        }
    }

    public function record(
        string $tipoContenido,
        int|string|null $referenciaId,
        ?int $usuarioId,
        array $analysis,
        array $payload
    ): bool {
        $notificacionEnviada = false;

        if ($tipoContenido === 'chat_ia') {
            return $this->notifyAdminsIfSecurityRisk($tipoContenido, $referenciaId, $usuarioId, $analysis, $payload);
        }

        if (!Schema::hasTable('moderacion_ia')) {
            Log::warning('No existe la tabla moderacion_ia; no se pudo registrar el analisis IA', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
            ]);
            return $notificacionEnviada;
        }

        try {
            $attributes = [
                'publicacion_id' => $this->publicacionIdFor($tipoContenido, $referenciaId, $payload),
                'foro_id' => $this->foroIdFor($tipoContenido, $referenciaId, $payload),
                'usuario_id' => $usuarioId,
                'contenido_analizado' => $this->contenidoAnalizado($payload),
                'categoria_detectada' => $analysis['categoria'] ?? 'otro',
                'tipo_riesgo' => $this->tipoRiesgo($analysis),
                'estado' => $this->visibilityFor($analysis['estado']),
                'riesgo' => $analysis['riesgo'],
                'razon' => $analysis['razon'],
                'modelo_ia' => $analysis['modelo_ia'] ?? $analysis['origen'] ?? 'desconocido',
                'procesado' => true,
                'revisado' => false,
                'revisado_por' => null,
                'decision_admin' => null,
            ];

            if (Schema::hasColumn('moderacion_ia', 'comentario_id')) {
                $attributes['comentario_id'] = $this->comentarioIdFor($tipoContenido, $referenciaId);
            }

            if (Schema::hasColumn('moderacion_ia', 'safety_ratings')) {
                $attributes['safety_ratings'] = $analysis['safety_ratings'] ?? [];
            }

            if (Schema::hasColumn('moderacion_ia', 'metadata')) {
                $attributes['metadata'] = [
                    'accion_recomendada' => $analysis['accion_recomendada'] ?? null,
                    'valor_educativo' => $analysis['valor_educativo'] ?? null,
                    'requiere_revision_humana' => $analysis['requiere_revision_humana'] ?? null,
                    'alerta_seguridad' => $analysis['alerta_seguridad'] ?? null,
                    'mensaje_usuario' => $analysis['mensaje_usuario'] ?? null,
                    'origen' => $analysis['origen'] ?? null,
                ];
            }

            $registro = ModeracionIa::create($attributes);

            $notificacionEnviada = $this->notifyAdminsIfSecurityRisk(
                $tipoContenido,
                $referenciaId,
                $usuarioId,
                $analysis,
                $payload,
                $registro->moderacion_id
            );

            Log::info('Analisis de moderacion IA registrado', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'estado' => $analysis['estado'],
                'riesgo' => $analysis['riesgo'],
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar el analisis de moderacion IA', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'columnas_esperadas' => [
                    'publicacion_id',
                    'foro_id',
                    'usuario_id',
                    'contenido_analizado',
                    'categoria_detectada',
                    'tipo_riesgo',
                    'estado',
                    'riesgo',
                    'razon',
                    'modelo_ia',
                    'procesado',
                    'revisado',
                    'revisado_por',
                    'decision_admin',
                    'created_at',
                    'updated_at',
                ],
                'error' => $exception->getMessage(),
            ]);

            return $notificacionEnviada;
        }
    }

    public function visibilityFor(string $estado): string
    {
        return match ($estado) {
            'permitido' => 'visible',
            'bloqueado' => 'bloqueado',
            default => 'revision',
        };
    }

    private function withUserModerationMessage(array $analysis, array $payload): array
    {
        $tipo = (string) ($payload['tipo_contenido'] ?? 'contenido');
        $estado = (string) ($analysis['estado'] ?? 'revision');
        $razon = trim((string) ($analysis['razon'] ?? ''));
        $nombreContenido = $this->nombreTipoContenido($tipo);

        if ($estado === 'permitido') {
            $analysis['mensaje_usuario'] = '';
            return $analysis;
        }

        if ($estado === 'bloqueado') {
            $analysis['mensaje_usuario'] = sprintf(
                'No fue posible publicar este %s porque violo las normas de seguridad y sana convivencia de Alejandria\'s Library.%s',
                $nombreContenido,
                $razon ? ' Motivo detectado: ' . $razon : ''
            );
            return $analysis;
        }

        $analysis['mensaje_usuario'] = sprintf(
            'Este %s fue enviado a revision porque podria incumplir las normas de seguridad y sana convivencia de Alejandria\'s Library.%s',
            $nombreContenido,
            $razon ? ' Motivo detectado: ' . $razon : ''
        );

        return $analysis;
    }

    private function nombreTipoContenido(string $tipo): string
    {
        return match ($tipo) {
            'foro' => 'foro',
            'publicacion' => 'publicacion',
            'comentario' => 'comentario',
            'comentario_respuesta' => 'subcomentario',
            default => 'contenido',
        };
    }

    private function publicacionIdFor(string $tipoContenido, int|string|null $referenciaId, array $payload): int|string|null
    {
        if ($tipoContenido === 'publicacion') {
            return $referenciaId;
        }

        return $payload['contexto']['publicacion_id'] ?? null;
    }

    private function foroIdFor(string $tipoContenido, int|string|null $referenciaId, array $payload): int|string|null
    {
        if ($tipoContenido === 'foro') {
            return $referenciaId;
        }

        return $payload['contexto']['foro_id'] ?? null;
    }

    private function comentarioIdFor(string $tipoContenido, int|string|null $referenciaId): int|string|null
    {
        return $tipoContenido === 'comentario' ? $referenciaId : null;
    }

    private function contenidoAnalizado(array $payload): string
    {
        $contenido = $payload['contenido'] ?? [];

        return trim(implode("\n", array_filter([
            $contenido['nombre'] ?? null,
            $contenido['titulo'] ?? null,
            $contenido['texto'] ?? null,
        ])));
    }

    private function tipoRiesgo(array $analysis): string
    {
        $alerta = $analysis['alerta_seguridad'] ?? [];
        if (($alerta['requiere_alerta'] ?? false) && !empty($alerta['tipo'])) {
            return (string) $alerta['tipo'];
        }

        $categoria = (string) ($analysis['categoria'] ?? 'otro');

        if (($analysis['estado'] ?? 'revision') === 'permitido' && in_array($categoria, ['educativo', 'conversacional', 'ocio', 'tecnologia', 'otro'], true)) {
            return 'ninguno';
        }

        return $categoria;
    }

    public function notifyAdminsIfSecurityRisk(
        string $tipoContenido,
        int|string|null $referenciaId,
        ?int $usuarioId,
        array $analysis,
        array $payload,
        int|string|null $moderacionId = null
    ): bool {
        $alerta = $analysis['alerta_seguridad'] ?? [];
        $estado = (string) ($analysis['estado'] ?? 'revision');
        $riesgo = (float) ($analysis['riesgo'] ?? 0);
        $categoria = (string) ($analysis['categoria'] ?? 'otro');

        $requiereNotificacion = ($alerta['requiere_alerta'] ?? false)
            || in_array($estado, ['revision', 'bloqueado'], true)
            || in_array($categoria, ['violencia', 'odio', 'autolesion'], true)
            || $riesgo >= 0.7;

        if (!$requiereNotificacion) {
            return false;
        }

        $usuario = $usuarioId ? Usuario::find($usuarioId) : null;

        try {
            app(AdminNotificationService::class)->notifyAiRiskDetected([
                'requiere_alerta' => true,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $this->nombreUsuario($usuario),
                'contenido' => $this->contenidoAnalizado($payload),
                'nivel' => ($alerta['requiere_alerta'] ?? false) ? ($alerta['nivel'] ?? $this->nivelRiesgo($analysis)) : $this->nivelRiesgo($analysis),
                'tipo' => ($alerta['requiere_alerta'] ?? false) ? ($alerta['tipo'] ?? $this->tipoRiesgo($analysis)) : $this->tipoRiesgo($analysis),
                'fecha' => now()->toDateTimeString(),
                'url' => '/admin/moderacion',
                'referencia_id' => $moderacionId ?? $referenciaId,
            ]);

            Log::warning('Notificacion interna por riesgo IA creada para admins', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'usuario_id' => $usuarioId,
                'nivel' => $alerta['nivel'] ?? null,
                'tipo_riesgo' => $alerta['tipo'] ?? null,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo crear notificacion interna por riesgo IA', [
                'tipo_contenido' => $tipoContenido,
                'referencia_id' => $referenciaId,
                'usuario_id' => $usuarioId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function nombreUsuario(?Usuario $usuario): string
    {
        if (!$usuario) {
            return 'Usuario desconocido';
        }

        return $usuario->usuario_apodo
            ?: trim(($usuario->usuario_nombre ?? '') . ' ' . ($usuario->usuario_apellido ?? ''))
            ?: $usuario->usuario_email
            ?: 'Usuario ' . $usuario->usuario_id;
    }

    private function nivelRiesgo(array $analysis): string
    {
        $riesgo = (float) ($analysis['riesgo'] ?? 0);

        if ($riesgo >= 0.9) {
            return 'riesgo_critico';
        }

        if ($riesgo >= 0.7) {
            return 'riesgo_alto';
        }

        return 'riesgo_medio';
    }

    private function urlContenido(string $tipoContenido, int|string|null $referenciaId, array $payload): string
    {
        if ($tipoContenido === 'chat_ia') {
            return '/admin/reportes';
        }

        if ($tipoContenido === 'foro' && $referenciaId) {
            return '/foro/' . $referenciaId;
        }

        if ($tipoContenido === 'publicacion' && $referenciaId) {
            $foroId = $payload['contexto']['foro_id'] ?? null;
            return $foroId ? '/foro/' . $foroId . '/publicacion/' . $referenciaId : '/admin/publicaciones';
        }

        if ($tipoContenido === 'comentario') {
            $publicacionId = $payload['contexto']['publicacion_id'] ?? null;
            $foroId = $payload['contexto']['foro_id'] ?? null;

            if ($foroId && $publicacionId) {
                return '/foro/' . $foroId . '/publicacion/' . $publicacionId;
            }
        }

        return '/admin/reportes';
    }

    private function withLocalSafetyAlert(array $analysis, array $payload): array
    {
        $alertaActual = $analysis['alerta_seguridad'] ?? [];
        if (($alertaActual['requiere_alerta'] ?? false) === true) {
            return $analysis;
        }

        $alertaLocal = $this->detectarAlertaSeguridadLocal($payload, $analysis);
        $analysis['alerta_seguridad'] = $alertaLocal;

        if ($alertaLocal['requiere_alerta']) {
            $analysis['riesgo'] = max((float) ($analysis['riesgo'] ?? 0), $this->riesgoMinimoPorNivel($alertaLocal['nivel']));
            if ($alertaLocal['tipo'] === 'autolesion') {
                $analysis['categoria'] = 'autolesion';
            }
            if (
                in_array($alertaLocal['nivel'], ['riesgo_alto', 'riesgo_critico'], true)
                && in_array($alertaLocal['tipo'], ['violencia', 'ilegal', 'odio', 'sexual', 'autolesion', 'acoso'], true)
            ) {
                $analysis['estado'] = 'bloqueado';
                $analysis['accion_recomendada'] = 'bloquear';
                $analysis['requiere_revision_humana'] = true;
                $analysis['categoria'] = $alertaLocal['tipo'] === 'acoso' ? ($analysis['categoria'] ?? 'acoso') : $alertaLocal['tipo'];
            }
        }

        return $analysis;
    }

    private function detectarAlertaSeguridadLocal(array $payload, array $analysis): array
    {
        $texto = $this->normalizarTexto($this->contenidoAnalizado($payload));
        $categoria = (string) ($analysis['categoria'] ?? 'otro');
        $riesgo = (float) ($analysis['riesgo'] ?? 0);

        $reglasCriticas = [
            '/\b(me voy a matar|voy a suicidarme|me suicidare|hoy me mato|quiero suicidarme hoy)\b/u' => ['autolesion', 'Intencion directa de suicidio o autolesion.'],
            '/\b(tengo un plan|ya tengo plan|tengo la cuerda|tengo pastillas|tengo un arma)\b.*\b(matarme|suicid|hacerme dano)\b/u' => ['autolesion', 'Planificacion de autolesion.'],
            '/\b(voy a matar a|voy a asesinar a|planeo matar|planeo asesinar|voy a disparar|voy a apunalar)\b/u' => ['violencia', 'Amenaza grave o planificacion de dano contra otras personas.'],
            '/\b(como matar|como asesinar|como puedo matar|como puedo asesinar|matar a mis profesor|matar a mis profesores)\b/u' => ['violencia', 'Solicitud o intencion relacionada con violencia grave.'],
            '/\b(trata de personas|trafico de personas|traficar personas|venta de personas|vendo.*(mujer|mujeres|nino|nina|menor|persona|humano|bebe))\b/u' => ['ilegal', 'Posible trata, trafico o venta de personas.'],
            '/\b(niños|ninos|menores).*\b(sexual|porno|explotacion)\b/u' => ['sexual', 'Posible explotacion sexual infantil.'],
            '/\b(como fabricar bombas|fabricar bombas|hacer explosivos|comprar drogas|vender drogas|comprar armas|vender armas)\b/u' => ['ilegal', 'Contenido ilegal o instrucciones daninas.'],
        ];

        $reglasAltas = [
            '/\b(me quiero matar|quiero matarme|quiero suicidarme|suicidarme|suicidio|me voy a cortar|quiero cortarme|autolesion|hacerme dano)\b/u' => ['autolesion', 'Senales explicitas de autolesion o suicidio.'],
            '/\b(te voy a matar|voy a matarte|quiero matarte|te matare|te voy a asesinar|voy a asesinarte|matar a todos|quiero matar a alguien)\b/u' => ['violencia', 'Amenaza o intencion explicita de violencia grave.'],
            '/\b(como puedo matar|como matar|como asesinar|matar a alguien|hacerle dano a alguien)\b/u' => ['violencia', 'Solicitud relacionada con violencia grave contra personas.'],
            '/\b(odio a|exterminar|discriminar).*\b(raza|religion|mujeres|hombres|grupo|personas)\b/u' => ['odio', 'Odio extremo o discriminacion grave.'],
            '/\b(publica su direccion|publica su telefono|doxx|filtra sus datos|extorsion|chantaje)\b/u' => ['acoso', 'Doxxing, extorsion o exposicion de datos personales.'],
            '/\b(hackea tarjetas bancarias|robar tarjetas|robar banco|phishing.*clave)\b/u' => ['ilegal', 'Hacking malicioso o fraude financiero.'],
        ];

        $reglasMedias = [
            '/\b(no quiero vivir|ya no quiero vivir|no puedo mas|no aguanto mas|quiero desaparecer|no tengo esperanza|sin esperanza|todo estaria mejor sin mi)\b/u' => ['salud_mental', 'Senales de desesperanza o crisis emocional intensa.'],
            '/\b(me siento vacio|me siento vacia|estoy desesperado|estoy desesperada|tristeza extrema)\b/u' => ['salud_mental', 'Expresion de tristeza extrema o malestar emocional.'],
        ];

        foreach ($reglasCriticas as $patron => [$tipo, $razon]) {
            if (preg_match($patron, $texto)) {
                return $this->alertaSeguridad(true, 'riesgo_critico', $tipo, $razon);
            }
        }

        foreach ($reglasAltas as $patron => [$tipo, $razon]) {
            if (preg_match($patron, $texto)) {
                return $this->alertaSeguridad(true, 'riesgo_alto', $tipo, $razon);
            }
        }

        if (in_array($categoria, ['autolesion', 'violencia', 'odio', 'ilegal', 'sexual'], true) && $riesgo >= 0.9) {
            return $this->alertaSeguridad(
                true,
                'riesgo_critico',
                $categoria,
                'La moderacion IA clasifico el contenido como riesgo grave.'
            );
        }

        foreach ($reglasMedias as $patron => [$tipo, $razon]) {
            if (preg_match($patron, $texto)) {
                return $this->alertaSeguridad(true, 'riesgo_medio', $tipo, $razon);
            }
        }

        return $this->alertaSeguridad(false, 'ninguno', 'ninguno', '');
    }

    private function alertaSeguridad(bool $requiereAlerta, string $nivel, string $tipo, string $razon): array
    {
        return [
            'requiere_alerta' => $requiereAlerta,
            'nivel' => $nivel,
            'tipo' => $tipo,
            'razon' => $razon,
        ];
    }

    private function riesgoMinimoPorNivel(string $nivel): float
    {
        return match ($nivel) {
            'riesgo_critico' => 0.93,
            'riesgo_alto' => 0.72,
            'riesgo_medio' => 0.38,
            default => 0.0,
        };
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $transliterado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return preg_replace('/\s+/', ' ', $transliterado !== false ? $transliterado : $texto) ?? $texto;
    }
}
