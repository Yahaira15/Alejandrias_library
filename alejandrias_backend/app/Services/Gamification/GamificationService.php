<?php

namespace App\Services\Gamification;

use App\Models\Comentario;
use App\Models\Foro;
use App\Models\Insignia;
use App\Models\Publicacion;
use App\Models\Sancion;
use App\Models\Usuario;
use App\Models\UsuarioInsignia;
use App\Models\UsuarioProgreso;
use App\Models\XpEvento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GamificationService
{
    private const POINTS = [
        'perfil_completo' => ['xp' => 80, 'cap' => null, 'window' => null],
        'crear_publicacion' => ['xp' => 35, 'cap' => 175, 'window' => 'week'],
        'crear_foro' => ['xp' => 80, 'cap' => 160, 'window' => 'week'],
        'comentario_creado' => ['xp' => 12, 'cap' => 96, 'window' => 'day'],
        'comentario_util' => ['xp' => 30, 'cap' => 150, 'window' => 'week'],
        'like_recibido' => ['xp' => 4, 'cap' => 100, 'window' => 'week'],
        'like_dado' => ['xp' => 2, 'cap' => 30, 'window' => 'day'],
        'comentario_recibido' => ['xp' => 6, 'cap' => 120, 'window' => 'week'],
        'lectura_publicacion' => ['xp' => 5, 'cap' => 60, 'window' => 'day'],
        'vista_recibida' => ['xp' => 1, 'cap' => 80, 'window' => 'week'],
        'tiempo_activo_10m' => ['xp' => 20, 'cap' => 60, 'window' => 'day'],
        'seguir_usuario' => ['xp' => 10, 'cap' => 100, 'window' => 'week'],
        'nuevo_seguidor' => ['xp' => 15, 'cap' => 150, 'window' => 'week'],
        'publicacion_destacada' => ['xp' => 120, 'cap' => null, 'window' => null],
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
        $xp = (int) $rule['xp'];
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
            return null;
        }

        $evento = XpEvento::create([
            'usuario_id' => $usuario->usuario_id,
            'accion' => $accion,
            'xp' => $xp,
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
            'metadata' => $metadata,
            'creado_en' => now(),
        ]);

        $this->syncProgress($usuario);

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
