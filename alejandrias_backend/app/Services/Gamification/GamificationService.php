<?php

namespace App\Services\Gamification;

use App\Models\Comentario;
use App\Models\Foro;
use App\Models\Insignia;
use App\Models\MisionDiaria;
use App\Models\Notificacion;
use App\Models\Publicacion;
use App\Models\RachaUsuario;
use App\Models\Sancion;
use App\Models\Usuario;
use App\Models\UsuarioInsignia;
use App\Models\UsuarioMision;
use App\Models\UsuarioProgreso;
use App\Models\XpEvento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GamificationService
{
    private const POINTS = [
        'perfil_completo' => ['xp' => 80, 'cap' => null, 'window' => null],
        'login_diario' => ['xp' => 5, 'cap' => 5, 'window' => 'day'],
        'entrar_hoy' => ['xp' => 5, 'cap' => 5, 'window' => 'day'],
        'crear_publicacion' => ['xp' => 25, 'cap' => 150, 'window' => 'week'],
        'crear_foro' => ['xp' => 80, 'cap' => 160, 'window' => 'week'],
        'comentario_creado' => ['xp' => 10, 'cap' => 80, 'window' => 'day'],
        'comentario_util' => ['xp' => 30, 'cap' => 150, 'window' => 'week'],
        'like_recibido' => ['xp' => 4, 'cap' => 100, 'window' => 'week'],
        'like_dado' => ['xp' => 2, 'cap' => 30, 'window' => 'day'],
        'comentario_recibido' => ['xp' => 6, 'cap' => 120, 'window' => 'week'],
        'lectura_publicacion' => ['xp' => 5, 'cap' => 60, 'window' => 'day'],
        'vista_recibida' => ['xp' => 1, 'cap' => 80, 'window' => 'week'],
        'tiempo_activo_10m' => ['xp' => 20, 'cap' => 60, 'window' => 'day'],
        'seguir_usuario' => ['xp' => 10, 'cap' => 100, 'window' => 'week'],
        'nuevo_seguidor' => ['xp' => 15, 'cap' => 150, 'window' => 'week'],
        'publicacion_destacada' => ['xp' => 50, 'cap' => null, 'window' => null],
        'publicacion_larga' => ['xp' => 15, 'cap' => 75, 'window' => 'week'],
        'usar_ia_educativa' => ['xp' => 8, 'cap' => 48, 'window' => 'day'],
        'ayudar_usuario' => ['xp' => 20, 'cap' => 120, 'window' => 'week'],
        'racha_diaria' => ['xp' => 0, 'cap' => null, 'window' => null],
        'mision_diaria' => ['xp' => 0, 'cap' => null, 'window' => null],
        'publicacion_popular' => ['xp' => 40, 'cap' => 120, 'window' => 'week'],
        'foro_activo' => ['xp' => 40, 'cap' => 120, 'window' => 'week'],
        'actividad_semanal' => ['xp' => 70, 'cap' => 70, 'window' => 'week'],
        'reporte_valido' => ['xp' => 25, 'cap' => 75, 'window' => 'week'],
        'registro_foro' => ['xp' => 20, 'cap' => 100, 'window' => 'week'],
        'sancion' => ['xp' => -100, 'cap' => null, 'window' => null],
        'publicacion_eliminada' => ['xp' => -60, 'cap' => null, 'window' => null],
    ];

    private const BADGES = [
        [
            'slug' => 'lider_aprendiz_atrio',
            'ruta' => 'lider',
            'nivel' => 1,
            'nombre' => 'Aprendiz del Atrio',
            'emoji' => '📜',
            'descripcion' => 'Primer paso como creador dentro de la biblioteca.',
            'requisito' => 'Perfil completo, 1 publicacion y 1 interaccion recibida.',
            'color' => '#B88A44',
            'criterios' => ['perfil_completo' => true, 'publicaciones' => 1, 'interacciones_recibidas' => 1],
        ],
        [
            'slug' => 'lider_portador_pergaminos',
            'ruta' => 'lider',
            'nivel' => 2,
            'nombre' => 'Portador de Pergaminos',
            'emoji' => '📚',
            'descripcion' => 'Comparte conocimiento con respuesta real de la comunidad.',
            'requisito' => '3 publicaciones, 3 comentarios recibidos y 50 XP por likes.',
            'color' => '#B88A44',
            'criterios' => ['publicaciones' => 3, 'comentarios_recibidos' => 3, 'xp_likes_recibidos' => 50],
        ],
        [
            'slug' => 'lider_escriba_faro',
            'ruta' => 'lider',
            'nivel' => 3,
            'nombre' => 'Escriba del Faro',
            'emoji' => '🖋️',
            'descripcion' => 'Ilumina rutas de aprendizaje con contenido y guia.',
            'requisito' => '5 publicaciones, 1 foro y 2 aportes utiles recibidos.',
            'color' => '#244C66',
            'criterios' => ['publicaciones' => 5, 'foros' => 1, 'comentarios_utiles' => 2],
        ],
        [
            'slug' => 'lider_guardian_sala',
            'ruta' => 'lider',
            'nivel' => 4,
            'nombre' => 'Guardian de Sala',
            'emoji' => '🏛️',
            'descripcion' => 'Construye espacios donde otros pueden reunirse y aprender.',
            'requisito' => '2 foros, 10 participantes acumulados y 5 dias activos.',
            'color' => '#244C66',
            'criterios' => ['foros' => 2, 'participantes' => 10, 'dias_activos' => 5],
        ],
        [
            'slug' => 'lider_cartografo_ideas',
            'ruta' => 'lider',
            'nivel' => 5,
            'nombre' => 'Cartografo de Ideas',
            'emoji' => '🧭',
            'descripcion' => 'Conecta temas distintos y abre rutas de pensamiento.',
            'requisito' => '8 publicaciones, 3 categorias y 300 vistas acumuladas.',
            'color' => '#2F6F5E',
            'criterios' => ['publicaciones' => 8, 'categorias' => 3, 'vistas' => 300],
        ],
        [
            'slug' => 'lider_mentor_archivo',
            'ruta' => 'lider',
            'nivel' => 6,
            'nombre' => 'Mentor del Archivo',
            'emoji' => '🦉',
            'descripcion' => 'Orienta a otros con aportes reconocidos por su valor.',
            'requisito' => '4 aportes utiles, 5 seguidores y 2 publicaciones con 10 likes.',
            'color' => '#7A2E3A',
            'criterios' => ['comentarios_utiles' => 4, 'seguidores' => 5, 'publicaciones_populares' => 2],
        ],
        [
            'slug' => 'lider_curador_alejandria',
            'ruta' => 'lider',
            'nivel' => 7,
            'nombre' => 'Curador de Alejandria',
            'emoji' => '⭐',
            'descripcion' => 'Su criterio convierte contenido en referencia comunitaria.',
            'requisito' => '1 publicacion destacada, 3 foros activos y 20 comentarios recibidos.',
            'color' => '#7A2E3A',
            'criterios' => ['publicaciones_destacadas' => 1, 'foros_activos' => 3, 'comentarios_recibidos' => 20],
        ],
        [
            'slug' => 'lider_maestro_faro',
            'ruta' => 'lider',
            'nivel' => 8,
            'nombre' => 'Maestro del Faro',
            'emoji' => '🌟',
            'descripcion' => 'Lider confiable que sostiene comunidad y conocimiento.',
            'requisito' => '2 destacadas, 15 seguidores, 1500 XP y 4 semanas activas.',
            'color' => '#F2C96D',
            'criterios' => ['publicaciones_destacadas' => 2, 'seguidores' => 15, 'xp_total' => 1500, 'semanas_activas' => 4],
        ],
        [
            'slug' => 'explorador_visitante_atrio',
            'ruta' => 'explorador',
            'nivel' => 1,
            'nombre' => 'Visitante del Atrio',
            'emoji' => '🧭',
            'descripcion' => 'Primer ingreso consciente al viaje del conocimiento.',
            'requisito' => 'Perfil completo, 3 lecturas registradas y 1 conexion.',
            'color' => '#2F6F5E',
            'criterios' => ['perfil_completo' => true, 'lecturas' => 3, 'conexiones' => 1],
        ],
        [
            'slug' => 'explorador_lector_pergaminos',
            'ruta' => 'explorador',
            'nivel' => 2,
            'nombre' => 'Lector de Pergaminos',
            'emoji' => '📖',
            'descripcion' => 'Lee y reacciona con intencion dentro de la biblioteca.',
            'requisito' => '30 minutos activos, 3 likes dados y 2 comentarios.',
            'color' => '#2F6F5E',
            'criterios' => ['minutos_activos' => 30, 'likes_dados' => 3, 'comentarios' => 2],
        ],
        [
            'slug' => 'explorador_caminante_archivo',
            'ruta' => 'explorador',
            'nivel' => 3,
            'nombre' => 'Caminante del Archivo',
            'emoji' => '🗺️',
            'descripcion' => 'Explora mas alla de un solo tema.',
            'requisito' => '3 categorias exploradas y comentarios en 3 dias distintos.',
            'color' => '#244C66',
            'criterios' => ['categorias' => 3, 'dias_con_comentarios' => 3],
        ],
        [
            'slug' => 'explorador_buscador_faro',
            'ruta' => 'explorador',
            'nivel' => 4,
            'nombre' => 'Buscador del Faro',
            'emoji' => '🔎',
            'descripcion' => 'Encuentra conversaciones y ayuda a otros a orientarse.',
            'requisito' => '5 comentarios, 2 utiles y 5 conexiones.',
            'color' => '#244C66',
            'criterios' => ['comentarios' => 5, 'comentarios_utiles' => 2, 'conexiones' => 5],
        ],
        [
            'slug' => 'explorador_navegante_ideas',
            'ruta' => 'explorador',
            'nivel' => 5,
            'nombre' => 'Navegante de Ideas',
            'emoji' => '🧭',
            'descripcion' => 'Participante constante que deja huella en distintas rutas.',
            'requisito' => '100 minutos activos, 10 likes dados y 5 respuestas recibidas.',
            'color' => '#B88A44',
            'criterios' => ['minutos_activos' => 100, 'likes_dados' => 10, 'respuestas_recibidas' => 5],
        ],
        [
            'slug' => 'explorador_discipulo_buho',
            'ruta' => 'explorador',
            'nivel' => 6,
            'nombre' => 'Discipulo del Buho',
            'emoji' => '🦉',
            'descripcion' => 'Aprende, pregunta y ayuda con criterio.',
            'requisito' => '6 aportes utiles, 2 semanas activas y 300 XP de exploracion.',
            'color' => '#7A2E3A',
            'criterios' => ['comentarios_utiles' => 6, 'semanas_activas' => 2, 'xp_exploracion' => 300],
        ],
        [
            'slug' => 'explorador_cronista_viaje',
            'ruta' => 'explorador',
            'nivel' => 7,
            'nombre' => 'Cronista del Viaje',
            'emoji' => '📜',
            'descripcion' => 'Convierte sus hallazgos en aportes para otros viajeros.',
            'requisito' => '2 publicaciones, 5 categorias y 1 publicacion destacada.',
            'color' => '#7A2E3A',
            'criterios' => ['publicaciones' => 2, 'categorias' => 5, 'publicaciones_destacadas' => 1],
        ],
        [
            'slug' => 'explorador_alejandria',
            'ruta' => 'explorador',
            'nivel' => 8,
            'nombre' => 'Explorador de Alejandria',
            'emoji' => '⭐',
            'descripcion' => 'Usuario integral que descubre, participa y conecta.',
            'requisito' => '1500 XP, 4 semanas activas, 12 utiles y 10 conexiones.',
            'color' => '#F2C96D',
            'criterios' => ['xp_total' => 1500, 'semanas_activas' => 4, 'comentarios_utiles' => 12, 'conexiones' => 10],
        ],
    ];

    public function definitions(): array
    {
        return [
            'puntos' => self::POINTS,
            'insignias' => self::BADGES,
            'paleta' => [
                'pergamino' => '#F4E8C8',
                'oro' => '#B88A44',
                'lider' => '#244C66',
                'explorador' => '#2F6F5E',
                'avanzado' => '#7A2E3A',
                'brillo' => '#F2C96D',
                'tinta' => '#2B2118',
            ],
        ];
    }

    public function award(Usuario $usuario, string $accion, ?Model $origen = null, array $metadata = []): ?XpEvento
    {
        if (!isset(self::POINTS[$accion]) || !Schema::hasTable('xp_evento')) {
            return null;
        }

        $rule = self::POINTS[$accion];
        $xp = array_key_exists('xp_override', $metadata)
            ? (int) $metadata['xp_override']
            : (int) $rule['xp'];
        $puntos = array_key_exists('puntos_override', $metadata)
            ? (int) $metadata['puntos_override']
            : max(0, $xp);
        $origenTipo = $origen ? class_basename($origen) : null;
        $origenId = $origen ? (int) $origen->getKey() : null;

        if ($origenTipo && $origenId && XpEvento::where([
            'usuario_id' => $usuario->usuario_id,
            'accion' => $accion,
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
        ])->exists()) {
            return null;
        }

        if (($rule['cap'] ?? null) !== null) {
            $inicio = ($rule['window'] ?? 'day') === 'week' ? now()->startOfWeek() : now()->startOfDay();
            $actual = XpEvento::where('usuario_id', $usuario->usuario_id)
                ->where('accion', $accion)
                ->where('creado_en', '>=', $inicio)
                ->sum('xp');
            $disponible = max(0, (int) $rule['cap'] - (int) $actual);
            $xp = min($xp, $disponible);
        }

        if ($xp === 0) {
            if (!($metadata['skip_mission_progress'] ?? false)) {
                $this->advanceMissionsForAction($usuario, $accion);
            }
            return null;
        }

        $nivelAnterior = $this->levelFromXp((int) XpEvento::where('usuario_id', $usuario->usuario_id)->sum('xp'));

        $evento = XpEvento::create([
            'usuario_id' => $usuario->usuario_id,
            'accion' => $accion,
            'xp' => $xp,
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
            'metadata' => $metadata,
            'creado_en' => now(),
        ]);

        if (Schema::hasColumn('usuario', 'usuario_experiencia')) {
            $usuario->increment('usuario_experiencia', $xp);
        }

        if ($puntos > 0 && Schema::hasColumn('usuario', 'usuario_puntos')) {
            $usuario->increment('usuario_puntos', $puntos);
        }

        if (!($metadata['skip_mission_progress'] ?? false)) {
            $this->advanceMissionsForAction($usuario, $accion);
        }
        $perfil = $this->syncProgress($usuario->fresh() ?? $usuario);
        $nivelNuevo = $this->levelFromXp((int) ($perfil['xp_total'] ?? 0));

        if ($nivelNuevo > $nivelAnterior) {
            $this->notify($usuario->usuario_id, 'level_up', 'Subiste al nivel ' . $nivelNuevo . ' en Alejandria.', '/perfil', $nivelNuevo);
        }

        return $evento;
    }

    public function syncProgress(Usuario $usuario): array
    {
        $this->seedBadges();

        $metricas = $this->metrics($usuario);
        $desbloqueadas = [];
        $niveles = ['lider' => 0, 'explorador' => 0];

        foreach (Insignia::orderBy('insignia_ruta')->orderBy('insignia_nivel')->get() as $insignia) {
            if (!$this->meetsCriteria($metricas, $insignia->insignia_criterios ?? [])) {
                continue;
            }

            UsuarioInsignia::firstOrCreate(
                [
                    'usuario_id' => $usuario->usuario_id,
                    'insignia_id' => $insignia->insignia_id,
                ],
                [
                    'obtenida_en' => now(),
                    'snapshot_metricas' => $metricas,
                ]
            );

            $niveles[$insignia->insignia_ruta] = max($niveles[$insignia->insignia_ruta] ?? 0, (int) $insignia->insignia_nivel);
            $desbloqueadas[] = $insignia->insignia_slug;
        }

        $rutaPrincipal = $this->mainRoute($usuario, $metricas);

        UsuarioProgreso::updateOrCreate(
            ['usuario_id' => $usuario->usuario_id],
            [
                'ruta_principal' => $rutaPrincipal,
                'xp_total' => $metricas['xp_total'],
                'nivel_lider' => $niveles['lider'],
                'nivel_explorador' => $niveles['explorador'],
                'metricas' => $metricas,
                'actualizado_en' => now(),
            ]
        );

        return $this->profile($usuario->fresh() ?? $usuario);
    }

    public function profile(Usuario $usuario): array
    {
        $this->seedBadges();
        $progreso = UsuarioProgreso::where('usuario_id', $usuario->usuario_id)->first();

        if (!$progreso) {
            return $this->syncProgress($usuario);
        }

        $obtenidas = UsuarioInsignia::where('usuario_id', $usuario->usuario_id)
            ->with('insignia')
            ->get()
            ->map(fn (UsuarioInsignia $item) => [
                'slug' => $item->insignia?->insignia_slug,
                'ruta' => $item->insignia?->insignia_ruta,
                'nivel' => $item->insignia?->insignia_nivel,
                'nombre' => $item->insignia?->insignia_nombre,
                'emoji' => $item->insignia?->insignia_emoji,
                'descripcion' => $item->insignia?->insignia_descripcion,
                'requisito' => $item->insignia?->insignia_requisito,
                'color' => $item->insignia?->insignia_color,
                'obtenida_en' => optional($item->obtenida_en)->toISOString(),
            ])
            ->filter(fn ($item) => $item['slug'])
            ->values();

        $obtenidasSlugs = $obtenidas->pluck('slug')->all();
        $catalogo = Insignia::orderBy('insignia_ruta')->orderBy('insignia_nivel')->get()
            ->map(fn (Insignia $insignia) => [
                'slug' => $insignia->insignia_slug,
                'ruta' => $insignia->insignia_ruta,
                'nivel' => $insignia->insignia_nivel,
                'nombre' => $insignia->insignia_nombre,
                'emoji' => $insignia->insignia_emoji,
                'descripcion' => $insignia->insignia_descripcion,
                'requisito' => $insignia->insignia_requisito,
                'color' => $insignia->insignia_color,
                'obtenida' => in_array($insignia->insignia_slug, $obtenidasSlugs, true),
            ])
            ->values();

        return [
            'ruta_principal' => $progreso->ruta_principal,
            'xp_total' => $progreso->xp_total,
            'nivel_lider' => $progreso->nivel_lider,
            'nivel_explorador' => $progreso->nivel_explorador,
            'metricas' => $progreso->metricas ?? [],
            'insignias_obtenidas' => $obtenidas,
            'catalogo' => $catalogo,
            'puntos' => self::POINTS,
        ];
    }

    public function panel(Usuario $usuario): array
    {
        return array_merge($this->profile($usuario), [
            'nivel_actual' => $this->levelFromXp((int) XpEvento::where('usuario_id', $usuario->usuario_id)->sum('xp')),
            'racha' => $this->streakPayload($usuario),
            'misiones' => $this->dailyMissions($usuario),
            'ranking' => [
                'global_xp' => $this->ranking('xp', 'global', 5),
                'semanal_xp' => $this->ranking('xp', 'semanal', 5),
            ],
            'estadisticas' => $this->metrics($usuario),
        ]);
    }

    public function recordDailyAccess(Usuario $usuario): array
    {
        if (!Schema::hasTable('racha_usuario')) {
            $this->award($usuario, 'login_diario');
            return ['racha' => null, 'misiones' => []];
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $racha = RachaUsuario::firstOrCreate(
            ['usuario_id' => $usuario->usuario_id],
            [
                'dias_consecutivos' => 0,
                'ultima_fecha' => null,
                'mejor_racha' => 0,
                'recompensa_reclamada' => false,
                'xp_obtenida_hoy' => 0,
            ]
        );

        if (!$racha->ultima_fecha || $racha->ultima_fecha->toDateString() !== $today) {
            $dias = $racha->ultima_fecha && $racha->ultima_fecha->toDateString() === $yesterday
                ? ((int) $racha->dias_consecutivos + 1)
                : 1;

            $racha->dias_consecutivos = $dias;
            $racha->ultima_fecha = $today;
            $racha->mejor_racha = max((int) $racha->mejor_racha, $dias);
            $racha->recompensa_reclamada = false;
            $racha->xp_obtenida_hoy = 0;
            $racha->actualizado_en = now();
            $racha->save();

            $this->award($usuario, 'login_diario', null, ['source' => 'daily_access']);
            $this->advanceMissionsForAction($usuario, 'entrar_hoy');

            if ($dias > 1 && $dias === (int) $racha->mejor_racha) {
                $this->notify($usuario->usuario_id, 'racha_record', 'Nuevo record de racha: ' . $dias . ' dias aprendiendo.', '/perfil', $dias);
            }
        }

        return [
            'racha' => $this->streakPayload($usuario),
            'misiones' => $this->dailyMissions($usuario),
        ];
    }

    public function claimStreakReward(Usuario $usuario): array
    {
        $this->recordDailyAccess($usuario);
        $racha = RachaUsuario::where('usuario_id', $usuario->usuario_id)->firstOrFail();

        if ($racha->recompensa_reclamada) {
            return $this->streakPayload($usuario);
        }

        $dias = (int) $racha->dias_consecutivos;
        $xp = match (true) {
            $dias >= 30 => 220,
            $dias >= 15 => 150,
            $dias >= 7 => 70,
            $dias >= 3 => 25,
            default => 10,
        };

        $this->award($usuario, 'racha_diaria', null, [
            'xp_override' => $xp,
            'puntos_override' => $xp,
            'dias_consecutivos' => $dias,
            'fecha' => now()->toDateString(),
        ]);

        $racha->recompensa_reclamada = true;
        $racha->xp_obtenida_hoy = $xp;
        $racha->actualizado_en = now();
        $racha->save();

        if ($dias >= 30) {
            $this->unlockBadgeBySlug($usuario, 'explorador_alejandria');
            $this->notify($usuario->usuario_id, 'logro_obtenido', 'Racha legendaria: 30 dias aprendiendo.', '/perfil', $dias);
        }

        return $this->streakPayload($usuario);
    }

    public function dailyMissions(Usuario $usuario): array
    {
        if (!Schema::hasTable('misiones_diarias') || !Schema::hasTable('usuario_mision')) {
            return [];
        }

        $misiones = $this->ensureTodayMissions();

        foreach ($misiones as $mision) {
            UsuarioMision::firstOrCreate([
                'usuario_id' => $usuario->usuario_id,
                'mision_diaria_id' => $mision->mision_diaria_id,
            ]);
        }

        $this->syncTodayMissionProgress($usuario);

        return UsuarioMision::with('mision')
            ->where('usuario_id', $usuario->usuario_id)
            ->whereHas('mision', fn ($query) => $query->whereDate('fecha', now()->toDateString())->where('activa', true))
            ->get()
            ->map(fn (UsuarioMision $item) => [
                'usuario_mision_id' => $item->usuario_mision_id,
                'slug' => $item->mision?->mision_slug,
                'titulo' => $item->mision?->mision_titulo,
                'descripcion' => $item->mision?->mision_descripcion,
                'tipo' => $item->mision?->mision_tipo,
                'objetivo' => (int) ($item->mision?->objetivo ?? 1),
                'progreso' => min((int) $item->progreso, (int) ($item->mision?->objetivo ?? 1)),
                'xp_recompensa' => (int) ($item->mision?->xp_recompensa ?? 0),
                'puntos_recompensa' => (int) ($item->mision?->puntos_recompensa ?? 0),
                'insignia_temporal' => $item->mision?->insignia_temporal,
                'completada' => (bool) $item->completada,
                'reclamada' => (bool) $item->reclamada,
            ])
            ->values()
            ->all();
    }

    private function syncTodayMissionProgress(Usuario $usuario): void
    {
        $today = now()->toDateString();
        $start = now()->startOfDay();
        $userId = $usuario->usuario_id;

        $progressByType = [
            'entrar_hoy' => RachaUsuario::where('usuario_id', $userId)
                ->whereDate('ultima_fecha', $today)
                ->exists() ? 1 : 0,
            'crear_publicacion' => Publicacion::where('publicacion_usuario_id', $userId)
                ->where('publicacion_fecha_creacion', '>=', $start)
                ->count(),
            'comentar' => Comentario::where('comentario_usuario_id', $userId)
                ->where('comentario_fecha_creacion', '>=', $start)
                ->count(),
            'usar_ia' => XpEvento::where('usuario_id', $userId)
                ->where('accion', 'usar_ia_educativa')
                ->where('creado_en', '>=', $start)
                ->count(),
            'leer_publicacion' => XpEvento::where('usuario_id', $userId)
                ->where('accion', 'lectura_publicacion')
                ->where('creado_en', '>=', $start)
                ->count(),
            'reaccionar_contenido' => XpEvento::where('usuario_id', $userId)
                ->where('accion', 'like_dado')
                ->where('creado_en', '>=', $start)
                ->count(),
        ];

        UsuarioMision::with('mision')
            ->where('usuario_id', $userId)
            ->where('reclamada', false)
            ->whereHas('mision', fn ($query) => $query->whereDate('fecha', $today)->where('activa', true))
            ->get()
            ->each(function (UsuarioMision $usuarioMision) use ($progressByType, $usuario) {
                $tipo = $usuarioMision->mision?->mision_tipo;
                if (!$tipo || !array_key_exists($tipo, $progressByType)) {
                    return;
                }

                $objetivo = (int) ($usuarioMision->mision?->objetivo ?? 1);
                $nuevoProgreso = min($objetivo, max((int) $usuarioMision->progreso, (int) $progressByType[$tipo]));

                if ($nuevoProgreso === (int) $usuarioMision->progreso) {
                    return;
                }

                $usuarioMision->progreso = $nuevoProgreso;
                if ($nuevoProgreso >= $objetivo && !$usuarioMision->completada) {
                    $usuarioMision->completada = true;
                    $usuarioMision->completada_en = now();
                    $this->notify($usuario->usuario_id, 'mision_completada', 'Mision completada: ' . $usuarioMision->mision->mision_titulo, '/perfil', $usuarioMision->usuario_mision_id);
                }
                $usuarioMision->save();
            });
    }

    public function claimMission(Usuario $usuario, int $usuarioMisionId): array
    {
        $usuarioMision = UsuarioMision::with('mision')
            ->where('usuario_id', $usuario->usuario_id)
            ->findOrFail($usuarioMisionId);

        if (!$usuarioMision->completada || $usuarioMision->reclamada || !$usuarioMision->mision) {
            return $this->missionPayload($usuarioMision);
        }

        $this->award($usuario, 'mision_diaria', $usuarioMision, [
            'xp_override' => (int) $usuarioMision->mision->xp_recompensa,
            'puntos_override' => (int) $usuarioMision->mision->puntos_recompensa,
            'mision' => $usuarioMision->mision->mision_slug,
        ]);

        $usuarioMision->reclamada = true;
        $usuarioMision->reclamada_en = now();
        $usuarioMision->save();

        return $this->missionPayload($usuarioMision->fresh('mision'));
    }

    public function advanceMissionsForAction(Usuario $usuario, string $accion, int $amount = 1): void
    {
        if (!Schema::hasTable('usuario_mision')) {
            return;
        }

        $types = match ($accion) {
            'comentario_creado' => ['comentar'],
            'crear_publicacion' => ['crear_publicacion'],
            'entrar_hoy', 'login_diario' => ['entrar_hoy'],
            'usar_ia_educativa' => ['usar_ia'],
            'ayudar_usuario', 'comentario_util' => ['ayudar_usuario'],
            'lectura_publicacion' => ['leer_publicacion'],
            'like_dado' => ['reaccionar_contenido'],
            default => [],
        };

        if (!$types) {
            return;
        }

        $this->dailyMissions($usuario);

        UsuarioMision::with('mision')
            ->where('usuario_id', $usuario->usuario_id)
            ->where('completada', false)
            ->whereHas('mision', fn ($query) => $query->whereDate('fecha', now()->toDateString())->whereIn('mision_tipo', $types))
            ->get()
            ->each(function (UsuarioMision $usuarioMision) use ($amount, $usuario) {
                $objetivo = (int) ($usuarioMision->mision?->objetivo ?? 1);
                $usuarioMision->progreso = min($objetivo, (int) $usuarioMision->progreso + $amount);
                if ($usuarioMision->progreso >= $objetivo) {
                    $usuarioMision->completada = true;
                    $usuarioMision->completada_en = now();
                    $this->notify($usuario->usuario_id, 'mision_completada', 'Mision completada: ' . $usuarioMision->mision->mision_titulo, '/perfil', $usuarioMision->usuario_mision_id);
                }
                $usuarioMision->save();
            });
    }

    public function trackAction(Usuario $usuario, string $accion, int $amount = 1): void
    {
        $this->advanceMissionsForAction($usuario, $accion, $amount);
    }

    public function ranking(string $tipo = 'xp', string $periodo = 'global', int $limit = 10, ?int $foroId = null): array
    {
        $cacheKey = 'ranking:' . $tipo . ':' . $periodo . ':' . ($foroId ?? 'global') . ':' . $limit;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tipo, $periodo, $limit, $foroId) {
            $since = $periodo === 'semanal' ? now()->startOfWeek() : null;

            if ($tipo === 'puntos') {
                return Usuario::query()
                    ->select('usuario_id', 'usuario_nombre', 'usuario_apodo', 'usuario_foto_perfil', 'usuario_puntos', 'usuario_experiencia')
                    ->orderByDesc('usuario_puntos')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Usuario $usuario, int $index) => $this->rankingUser($usuario, $index, (int) ($usuario->usuario_puntos ?? 0)))
                    ->all();
            }

            if ($tipo === 'publicaciones') {
                $query = Publicacion::query()
                    ->select('publicacion_usuario_id as usuario_id', DB::raw('count(*) as total'))
                    ->when($since, fn ($q) => $q->where('publicacion_fecha_creacion', '>=', $since))
                    ->when($foroId, fn ($q) => $q->where('publicacion_foro_id', $foroId))
                    ->groupBy('publicacion_usuario_id');
            } elseif ($tipo === 'comentarios') {
                $query = Comentario::query()
                    ->select('comentario_usuario_id as usuario_id', DB::raw('count(*) as total'))
                    ->when($since, fn ($q) => $q->where('comentario_id', '>', 0))
                    ->when($foroId, function ($q) use ($foroId) {
                        $q->join('publicacion', 'comentario.comentario_publicacion_id', '=', 'publicacion.publicacion_id')
                            ->where('publicacion.publicacion_foro_id', $foroId);
                    })
                    ->groupBy('comentario_usuario_id');
            } elseif ($tipo === 'xp' && !$since) {
                return Usuario::query()
                    ->leftJoin('xp_evento', 'usuario.usuario_id', '=', 'xp_evento.usuario_id')
                    ->select(
                        'usuario.usuario_id',
                        'usuario.usuario_nombre',
                        'usuario.usuario_apodo',
                        'usuario.usuario_foto_perfil',
                        'usuario.usuario_experiencia',
                        'usuario.usuario_puntos',
                        DB::raw('GREATEST(COALESCE(usuario.usuario_experiencia, 0), COALESCE(SUM(xp_evento.xp), 0)) as total')
                    )
                    ->groupBy(
                        'usuario.usuario_id',
                        'usuario.usuario_nombre',
                        'usuario.usuario_apodo',
                        'usuario.usuario_foto_perfil',
                        'usuario.usuario_experiencia',
                        'usuario.usuario_puntos'
                    )
                    ->havingRaw('GREATEST(COALESCE(usuario.usuario_experiencia, 0), COALESCE(SUM(xp_evento.xp), 0)) > 0')
                    ->orderByDesc('total')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($row, int $index) => [
                        'posicion' => $index + 1,
                        'usuario_id' => $row->usuario_id,
                        'nombre' => $row->usuario_nombre ?: $row->usuario_apodo,
                        'apodo' => $row->usuario_apodo,
                        'avatar' => $row->usuario_foto_perfil,
                        'nivel' => $this->levelFromXp((int) $row->total),
                        'xp' => (int) $row->total,
                        'puntos' => (int) ($row->usuario_puntos ?? 0),
                        'total' => (int) $row->total,
                        'medalla' => ['oro', 'plata', 'bronce'][$index] ?? null,
                    ])
                    ->all();
            } else {
                $query = XpEvento::query()
                    ->select('usuario_id', DB::raw('sum(xp) as total'))
                    ->when($since, fn ($q) => $q->where('creado_en', '>=', $since))
                    ->groupBy('usuario_id');
            }

            $rows = DB::query()
                ->fromSub($query, 'ranking')
                ->join('usuario', 'usuario.usuario_id', '=', 'ranking.usuario_id')
                ->select('usuario.usuario_id', 'usuario.usuario_nombre', 'usuario.usuario_apodo', 'usuario.usuario_foto_perfil', 'usuario.usuario_experiencia', 'usuario.usuario_puntos', 'ranking.total')
                ->orderByDesc('ranking.total')
                ->limit($limit)
                ->get();

            return $rows->map(fn ($row, int $index) => [
                'posicion' => $index + 1,
                'usuario_id' => $row->usuario_id,
                'nombre' => $row->usuario_nombre ?: $row->usuario_apodo,
                'apodo' => $row->usuario_apodo,
                'avatar' => $row->usuario_foto_perfil,
                'nivel' => $this->levelFromXp((int) ($row->usuario_experiencia ?? 0)),
                'xp' => (int) ($row->usuario_experiencia ?? 0),
                'puntos' => (int) ($row->usuario_puntos ?? 0),
                'total' => (int) $row->total,
                'medalla' => ['oro', 'plata', 'bronce'][$index] ?? null,
            ])->all();
        });
    }

    public function grantDemoBadges(Usuario $usuario): array
    {
        $this->seedBadges();

        if (!Schema::hasTable('usuario_insignia') || !Schema::hasTable('xp_evento')) {
            return $this->profile($usuario);
        }

        $demoSlugs = [
            'explorador_visitante_atrio',
            'explorador_lector_pergaminos',
            'explorador_caminante_archivo',
            'lider_aprendiz_atrio',
            'lider_portador_pergaminos',
        ];

        $snapshot = array_merge($this->metrics($usuario), [
            'demo' => true,
            'nota' => 'Insignia agregada para previsualizar el modulo de logros.',
        ]);

        foreach (Insignia::whereIn('insignia_slug', $demoSlugs)->get() as $insignia) {
            UsuarioInsignia::firstOrCreate(
                [
                    'usuario_id' => $usuario->usuario_id,
                    'insignia_id' => $insignia->insignia_id,
                ],
                [
                    'obtenida_en' => now(),
                    'snapshot_metricas' => $snapshot,
                ]
            );
        }

        XpEvento::firstOrCreate(
            [
                'usuario_id' => $usuario->usuario_id,
                'accion' => 'demo_logros',
                'origen_tipo' => 'Demo',
                'origen_id' => 1,
            ],
            [
                'xp' => 450,
                'metadata' => ['demo' => true],
                'creado_en' => now(),
            ]
        );

        UsuarioProgreso::updateOrCreate(
            ['usuario_id' => $usuario->usuario_id],
            [
                'ruta_principal' => $usuario->usuario_rol === 'lider' ? 'lider' : 'explorador',
                'xp_total' => (int) XpEvento::where('usuario_id', $usuario->usuario_id)->sum('xp'),
                'nivel_lider' => 2,
                'nivel_explorador' => 3,
                'metricas' => array_merge($snapshot, ['xp_total' => 450]),
                'actualizado_en' => now(),
            ]
        );

        return $this->profile($usuario);
    }

    private function seedBadges(): void
    {
        if (!Schema::hasTable('insignia')) {
            return;
        }

        foreach (self::BADGES as $badge) {
            Insignia::updateOrCreate(
                ['insignia_slug' => $badge['slug']],
                [
                    'insignia_ruta' => $badge['ruta'],
                    'insignia_nivel' => $badge['nivel'],
                    'insignia_nombre' => $badge['nombre'],
                    'insignia_emoji' => $badge['emoji'],
                    'insignia_descripcion' => $badge['descripcion'],
                    'insignia_requisito' => $badge['requisito'],
                    'insignia_criterios' => $badge['criterios'],
                    'insignia_color' => $badge['color'],
                ]
            );
        }
    }

    private function ensureTodayMissions(): Collection
    {
        $today = now()->toDateString();
        $templates = [
            ['mision_slug' => 'entrar_hoy', 'mision_titulo' => 'Abrir la biblioteca', 'mision_descripcion' => 'Ingresa hoy y conserva el ritmo de aprendizaje.', 'mision_tipo' => 'entrar_hoy', 'objetivo' => 1, 'xp_recompensa' => 10, 'puntos_recompensa' => 10, 'insignia_temporal' => 'calendario_streak'],
            ['mision_slug' => 'comentar_3', 'mision_titulo' => 'Conversador educativo', 'mision_descripcion' => 'Comenta 3 veces con aportes claros y respetuosos.', 'mision_tipo' => 'comentar', 'objetivo' => 3, 'xp_recompensa' => 35, 'puntos_recompensa' => 25, 'insignia_temporal' => 'scroll_mision'],
            ['mision_slug' => 'crear_1_publicacion', 'mision_titulo' => 'Nueva idea al archivo', 'mision_descripcion' => 'Crea una publicacion educativa en un foro.', 'mision_tipo' => 'crear_publicacion', 'objetivo' => 1, 'xp_recompensa' => 45, 'puntos_recompensa' => 35, 'insignia_temporal' => 'libro_magico'],
            ['mision_slug' => 'usar_ia', 'mision_titulo' => 'Consulta al cerebro IA', 'mision_descripcion' => 'Usa la IA educativa para profundizar un tema.', 'mision_tipo' => 'usar_ia', 'objetivo' => 1, 'xp_recompensa' => 18, 'puntos_recompensa' => 12, 'insignia_temporal' => 'cerebro_ia'],
            ['mision_slug' => 'leer_2_publicaciones', 'mision_titulo' => 'Lector activo', 'mision_descripcion' => 'Lee 2 publicaciones de la comunidad.', 'mision_tipo' => 'leer_publicacion', 'objetivo' => 2, 'xp_recompensa' => 20, 'puntos_recompensa' => 15, 'insignia_temporal' => 'pergamino'],
        ];

        foreach ($templates as $template) {
            MisionDiaria::firstOrCreate(
                ['mision_slug' => $template['mision_slug'], 'fecha' => $today],
                array_merge($template, ['fecha' => $today, 'activa' => true])
            );
        }

        return MisionDiaria::whereDate('fecha', $today)
            ->where('activa', true)
            ->orderBy('mision_diaria_id')
            ->get();
    }

    private function streakPayload(Usuario $usuario): ?array
    {
        if (!Schema::hasTable('racha_usuario')) {
            return null;
        }

        $racha = RachaUsuario::where('usuario_id', $usuario->usuario_id)->first();
        if (!$racha) {
            return null;
        }

        $dias = (int) $racha->dias_consecutivos;

        return [
            'dias_consecutivos' => $dias,
            'ultima_fecha' => optional($racha->ultima_fecha)->toDateString(),
            'mejor_racha' => (int) $racha->mejor_racha,
            'recompensa_reclamada' => (bool) $racha->recompensa_reclamada,
            'xp_obtenida_hoy' => (int) $racha->xp_obtenida_hoy,
            'mensaje' => 'Llevas ' . $dias . ' dias aprendiendo',
            'calendario' => collect(range(6, 0))->map(fn ($offset) => [
                'fecha' => now()->subDays($offset)->toDateString(),
                'activo' => $racha->ultima_fecha && now()->subDays($offset)->greaterThanOrEqualTo(now()->subDays(max(0, $dias - 1))->startOfDay()) && now()->subDays($offset)->lessThanOrEqualTo(now()),
            ])->values()->all(),
            'siguiente_recompensa' => match (true) {
                $dias < 3 => ['dia' => 3, 'xp' => 25],
                $dias < 7 => ['dia' => 7, 'xp' => 70],
                $dias < 15 => ['dia' => 15, 'xp' => 150],
                $dias < 30 => ['dia' => 30, 'xp' => 220],
                default => ['dia' => $dias + 1, 'xp' => 220],
            },
        ];
    }

    private function missionPayload(UsuarioMision $usuarioMision): array
    {
        return [
            'usuario_mision_id' => $usuarioMision->usuario_mision_id,
            'slug' => $usuarioMision->mision?->mision_slug,
            'titulo' => $usuarioMision->mision?->mision_titulo,
            'progreso' => (int) $usuarioMision->progreso,
            'objetivo' => (int) ($usuarioMision->mision?->objetivo ?? 1),
            'completada' => (bool) $usuarioMision->completada,
            'reclamada' => (bool) $usuarioMision->reclamada,
        ];
    }

    private function rankingUser(Usuario $usuario, int $index, int $total): array
    {
        return [
            'posicion' => $index + 1,
            'usuario_id' => $usuario->usuario_id,
            'nombre' => $usuario->usuario_nombre ?: $usuario->usuario_apodo,
            'apodo' => $usuario->usuario_apodo,
            'avatar' => $usuario->usuario_foto_perfil,
            'nivel' => $this->levelFromXp((int) ($usuario->usuario_experiencia ?? 0)),
            'xp' => (int) ($usuario->usuario_experiencia ?? 0),
            'puntos' => (int) ($usuario->usuario_puntos ?? 0),
            'total' => $total,
            'medalla' => ['oro', 'plata', 'bronce'][$index] ?? null,
        ];
    }

    private function notify(int $usuarioId, string $tipo, string $contenido, string $url, ?int $referenciaId = null): void
    {
        if (!Schema::hasTable('notificacion')) {
            return;
        }

        Notificacion::create([
            'notificacion_usuario_id' => $usuarioId,
            'notificacion_tipo' => $tipo,
            'notificacion_contenido' => $contenido,
            'notificacion_leida' => false,
            'notificacion_fecha' => now(),
            'notificacion_url' => $url,
            'notificacion_referencia_id' => $referenciaId,
        ]);
    }

    private function unlockBadgeBySlug(Usuario $usuario, string $slug): void
    {
        $this->seedBadges();
        $insignia = Insignia::where('insignia_slug', $slug)->first();

        if (!$insignia) {
            return;
        }

        UsuarioInsignia::firstOrCreate(
            ['usuario_id' => $usuario->usuario_id, 'insignia_id' => $insignia->insignia_id],
            ['obtenida_en' => now(), 'snapshot_metricas' => $this->metrics($usuario)]
        );
    }

    public function levelFromXp(int $xp): int
    {
        return match (true) {
            $xp >= 2200 => 8,
            $xp >= 1700 => 7,
            $xp >= 1350 => 6,
            $xp >= 1050 => 5,
            $xp >= 760 => 4,
            $xp >= 500 => 3,
            $xp >= 280 => 2,
            $xp >= 120 => 1,
            default => 0,
        };
    }

    private function metrics(Usuario $usuario): array
    {
        $userId = $usuario->usuario_id;
        $xpTotal = Schema::hasTable('xp_evento')
            ? (int) XpEvento::where('usuario_id', $userId)->sum('xp')
            : (int) ($usuario->usuario_experiencia ?? 0);

        $foros = Schema::hasTable('foro') ? Foro::where('foro_creador_id', $userId)->count() : 0;
        $publicaciones = Schema::hasTable('publicacion') ? Publicacion::where('publicacion_usuario_id', $userId)->count() : 0;
        $comentarios = Schema::hasTable('comentario') ? Comentario::where('comentario_usuario_id', $userId)->count() : 0;
        $comentariosRecibidos = 0;
        $categorias = 0;
        $participantes = 0;
        $destacadas = 0;

        if (Schema::hasTable('publicacion') && Schema::hasTable('comentario')) {
            $comentariosRecibidos = Comentario::join('publicacion', 'comentario.comentario_publicacion_id', '=', 'publicacion.publicacion_id')
                ->where('publicacion.publicacion_usuario_id', $userId)
                ->where('comentario.comentario_usuario_id', '!=', $userId)
                ->count();
        }

        if (Schema::hasTable('publicacion')) {
            $destacadas = Publicacion::where('publicacion_usuario_id', $userId)
                ->where('publicacion_destacada', true)
                ->count();
        }

        if (Schema::hasTable('publicacion') && Schema::hasTable('foro')) {
            $categorias = Publicacion::join('foro', 'publicacion.publicacion_foro_id', '=', 'foro.foro_id')
                ->where('publicacion.publicacion_usuario_id', $userId)
                ->distinct('foro.foro_categoria_id')
                ->count('foro.foro_categoria_id');
        }

        if (Schema::hasTable('foro_usuario') && Schema::hasTable('foro')) {
            $participantes = DB::table('foro_usuario')
                ->join('foro', 'foro_usuario.foro_id', '=', 'foro.foro_id')
                ->where('foro.foro_creador_id', $userId)
                ->where('foro_usuario.usuario_id', '!=', $userId)
                ->distinct('foro_usuario.usuario_id')
                ->count('foro_usuario.usuario_id');
        }

        $xpByAction = fn (string $action): int => Schema::hasTable('xp_evento')
            ? (int) XpEvento::where('usuario_id', $userId)->where('accion', $action)->sum('xp')
            : 0;
        $countByAction = fn (string $action): int => Schema::hasTable('xp_evento')
            ? (int) XpEvento::where('usuario_id', $userId)->where('accion', $action)->count()
            : 0;

        $eventDates = Schema::hasTable('xp_evento')
            ? XpEvento::where('usuario_id', $userId)->get(['creado_en'])
            : collect();
        $diasActivos = $eventDates
            ->map(fn (XpEvento $evento) => optional($evento->creado_en)->toDateString())
            ->filter()
            ->unique()
            ->count();
        $semanasActivas = $eventDates
            ->map(fn (XpEvento $evento) => optional($evento->creado_en)->format('o-W'))
            ->filter()
            ->unique()
            ->count();
        $diasConComentarios = min($diasActivos, $comentarios);

        $seguidores = $countByAction('nuevo_seguidor');
        $conexiones = $countByAction('seguir_usuario') + $countByAction('registro_foro');
        $likesDados = $countByAction('like_dado');
        $vistas = $countByAction('vista_recibida') + ($xpByAction('vista_recibida') * 5);
        $lecturas = $countByAction('lectura_publicacion') + (int) floor($xpByAction('tiempo_activo_10m') / 20);

        return [
            'perfil_completo' => $this->profileIsComplete($usuario),
            'xp_total' => $xpTotal,
            'publicaciones' => $publicaciones,
            'foros' => $foros,
            'comentarios' => $comentarios,
            'comentarios_recibidos' => $comentariosRecibidos,
            'interacciones_recibidas' => $comentariosRecibidos + $countByAction('like_recibido'),
            'comentarios_utiles' => $countByAction('comentario_util'),
            'xp_likes_recibidos' => $xpByAction('like_recibido'),
            'participantes' => $participantes,
            'dias_activos' => $diasActivos,
            'categorias' => $categorias,
            'vistas' => $vistas,
            'seguidores' => $seguidores,
            'publicaciones_populares' => $countByAction('publicacion_popular'),
            'publicaciones_destacadas' => $destacadas + $countByAction('publicacion_destacada'),
            'foros_activos' => min($foros, $countByAction('foro_activo') + $foros),
            'semanas_activas' => $semanasActivas,
            'lecturas' => $lecturas,
            'conexiones' => $conexiones,
            'minutos_activos' => $countByAction('tiempo_activo_10m') * 10,
            'likes_dados' => $likesDados,
            'dias_con_comentarios' => min($diasActivos, max($diasConComentarios, $comentarios > 0 ? 1 : 0)),
            'respuestas_recibidas' => $comentariosRecibidos,
            'xp_exploracion' => $xpByAction('comentario_creado') + $xpByAction('registro_foro') + $xpByAction('tiempo_activo_10m'),
            'sanciones_recientes' => $this->recentSanctions($userId),
        ];
    }

    private function meetsCriteria(array $metricas, array $criterios): bool
    {
        if (($metricas['sanciones_recientes'] ?? 0) > 0 && ($criterios['xp_total'] ?? 0) >= 1500) {
            return false;
        }

        foreach ($criterios as $key => $expected) {
            $actual = $metricas[$key] ?? 0;
            if (is_bool($expected) && (bool) $actual !== $expected) {
                return false;
            }
            if (!is_bool($expected) && $actual < $expected) {
                return false;
            }
        }

        return true;
    }

    private function profileIsComplete(Usuario $usuario): bool
    {
        return filled($usuario->usuario_nombre)
            && filled($usuario->usuario_apodo)
            && filled($usuario->usuario_email)
            && filled($usuario->usuario_bio)
            && is_array($usuario->usuario_intereses)
            && count($usuario->usuario_intereses) > 0;
    }

    private function recentSanctions(int $userId): int
    {
        if (!Schema::hasTable('sancion')) {
            return 0;
        }

        return Sancion::where('sancion_usuario_id', $userId)
            ->where('sancion_fecha_inicio', '>=', now()->subDays(30))
            ->count();
    }

    private function mainRoute(Usuario $usuario, array $metricas): string
    {
        if (in_array($usuario->usuario_rol, ['lider', 'admin'], true)) {
            return 'lider';
        }

        $liderScore = ($metricas['publicaciones'] * 3) + ($metricas['foros'] * 4) + $metricas['comentarios_recibidos'];
        $explorerScore = ($metricas['comentarios'] * 2) + $metricas['lecturas'] + $metricas['conexiones'];

        return $liderScore > $explorerScore ? 'lider' : 'explorador';
    }
}
