<?php

namespace App\Http\Controllers\ApiReporte;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Foro;
use App\Models\Notificacion;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\Usuario;
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteController extends Controller
{
    private array $tipos = ['publicacion', 'comentario', 'usuario', 'foro'];
    private array $estados = ['pendiente', 'revisando', 'aprobado', 'rechazado'];
    private array $prioridades = ['baja', 'media', 'alta', 'critica'];

    public function index()
    {
        $reportes = Reporte::with(['reporta', 'revisor', 'sanciones.usuario'])
            ->orderByRaw("CASE reporte_prioridad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END")
            ->orderBy('reporte_fecha', 'desc')
            ->get()
            ->map(fn ($reporte) => $this->appendReference($reporte));

        return response()->json($reportes, 200);
    }

    public function store(Request $request)
    {
        $usuario = auth('sanctum')->user();

        if (!$usuario) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'reporte_tipo' => ['required', Rule::in($this->tipos)],
            'reporte_referencia_id' => 'required|integer|min:1',
            'reporte_motivo' => 'required|string|max:100',
            'reporte_descripcion' => 'nullable|string|max:2000',
            'reporte_prioridad' => ['nullable', Rule::in($this->prioridades)],
        ]);

        if (!$this->referenceExists($data['reporte_tipo'], (int) $data['reporte_referencia_id'])) {
            return response()->json(['error' => 'El elemento reportado no existe'], 404);
        }

        $reporte = Reporte::create([
            ...$data,
            'reporte_usuario_reporta_id' => $usuario->usuario_id,
            'reporte_estado' => 'pendiente',
            'reporte_prioridad' => $data['reporte_prioridad'] ?? $this->priorityFromReason($data['reporte_motivo']),
            'reporte_fecha' => now(),
        ]);

        app(AdminNotificationService::class)->notifyReportCreated($reporte);

        return response()->json($this->appendReference($reporte->load('reporta')), 201);
    }

    public function show($id)
    {
        $reporte = Reporte::with(['reporta', 'revisor', 'sanciones.usuario'])->findOrFail($id);

        return response()->json($this->appendReference($reporte), 200);
    }

    public function update(Request $request, $id)
    {
        $admin = auth('sanctum')->user();
        $reporte = Reporte::findOrFail($id);

        $data = $request->validate([
            'reporte_estado' => ['required', Rule::in($this->estados)],
            'reporte_prioridad' => ['required', Rule::in($this->prioridades)],
            'decision_final' => 'nullable|string|max:100',
            'riesgo' => 'nullable|numeric|min:0|max:100',
            'ia_detectado' => 'nullable|boolean',
        ]);

        $reporte->update([
            ...$data,
            'revisado_por' => $admin?->usuario_id,
        ]);

        return response()->json($this->appendReference($reporte->fresh(['reporta', 'revisor', 'sanciones.usuario'])), 200);
    }

    public function destroy($id)
    {
        Reporte::findOrFail($id)->delete();

        return response()->json(['mensaje' => 'Reporte eliminado'], 200);
    }

    public function sancionar(Request $request, $id)
    {
        $admin = auth('sanctum')->user();
        $reporte = Reporte::findOrFail($id);

        $data = $request->validate([
            'sancion_usuario_id' => 'required|exists:usuario,usuario_id',
            'sancion_tipo' => 'required|in:advertencia,restriccion,suspension,ban',
            'sancion_motivo' => 'required|string|max:2000',
            'duracion' => 'nullable|in:1h,24h,7d,30d,permanente',
        ]);

        $config = $this->sanctionConfig($data['sancion_tipo'], $data['duracion'] ?? null);

        $sancion = Sancion::create([
            'sancion_usuario_id' => $data['sancion_usuario_id'],
            'sancion_tipo' => $data['sancion_tipo'],
            'sancion_nivel' => $config['nivel'],
            'sancion_motivo' => $data['sancion_motivo'],
            'sancion_fecha_inicio' => now(),
            'sancion_fecha_fin' => $config['fin'],
            'sancion_activa' => true,
            'sancion_admin_id' => $admin?->usuario_id,
            'sancion_reporte_id' => $reporte->reporte_id,
            'bloquea_comentar' => $config['bloquea_comentar'],
            'bloquea_publicar' => $config['bloquea_publicar'],
            'bloquea_login' => $config['bloquea_login'],
        ]);

        if ($config['bloquea_login']) {
            Usuario::where('usuario_id', $data['sancion_usuario_id'])->update(['usuario_bloqueado' => true]);
        }

        $reporte->update([
            'reporte_estado' => 'aprobado',
            'revisado_por' => $admin?->usuario_id,
            'decision_final' => 'Sancion aplicada: ' . $data['sancion_tipo'],
        ]);

        if (in_array($data['sancion_tipo'], ['advertencia', 'restriccion'], true)) {
            Notificacion::create([
                'notificacion_usuario_id' => $data['sancion_usuario_id'],
                'notificacion_tipo' => 'sancion_' . $data['sancion_tipo'],
                'notificacion_contenido' => $this->notificationMessage($data['sancion_tipo'], $data['sancion_motivo'], $config['fin']),
                'notificacion_leida' => false,
                'notificacion_fecha' => now(),
                'notificacion_url' => '/perfil',
                'notificacion_referencia_id' => $sancion->sancion_id,
            ]);
        }

        return response()->json($sancion->load(['usuario', 'admin', 'reporte']), 201);
    }

    private function notificationMessage(string $tipo, string $motivo, $fechaFin): string
    {
        if ($tipo === 'restriccion') {
            $fin = $fechaFin ? $fechaFin->format('Y-m-d H:i') : 'sin fecha de finalizacion';

            return 'Tu cuenta recibio una restriccion temporal hasta ' . $fin . '. Motivo: ' . $motivo;
        }

        return 'Tu cuenta recibio una advertencia. Motivo: ' . $motivo;
    }

    private function appendReference(Reporte $reporte): Reporte
    {
        $reporte->setAttribute('referencia', match ($reporte->reporte_tipo) {
            'publicacion' => Publicacion::with('usuario')->find($reporte->reporte_referencia_id),
            'comentario' => Comentario::with('usuario')->find($reporte->reporte_referencia_id),
            'usuario' => Usuario::find($reporte->reporte_referencia_id),
            'foro' => Foro::with('usuario')->find($reporte->reporte_referencia_id),
            default => null,
        });

        return $reporte;
    }

    private function referenceExists(string $tipo, int $id): bool
    {
        return match ($tipo) {
            'publicacion' => Publicacion::where('publicacion_id', $id)->exists(),
            'comentario' => Comentario::where('comentario_id', $id)->exists(),
            'usuario' => Usuario::where('usuario_id', $id)->exists(),
            'foro' => Foro::where('foro_id', $id)->exists(),
            default => false,
        };
    }

    private function priorityFromReason(string $motivo): string
    {
        $motivo = strtolower($motivo);

        if (str_contains($motivo, 'ilegal') || str_contains($motivo, 'amenaza') || str_contains($motivo, 'odio')) {
            return 'critica';
        }

        if (str_contains($motivo, 'acoso') || str_contains($motivo, 'violencia') || str_contains($motivo, 'sexual')) {
            return 'alta';
        }

        return 'media';
    }

    private function sanctionConfig(string $tipo, ?string $duracion): array
    {
        $fin = match ($duracion) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            default => null,
        };

        return match ($tipo) {
            'advertencia' => ['nivel' => 1, 'fin' => null, 'bloquea_comentar' => false, 'bloquea_publicar' => false, 'bloquea_login' => false],
            'restriccion' => ['nivel' => 2, 'fin' => $fin ?? now()->addDay(), 'bloquea_comentar' => true, 'bloquea_publicar' => true, 'bloquea_login' => false],
            'suspension' => ['nivel' => 3, 'fin' => $fin ?? now()->addDays(7), 'bloquea_comentar' => true, 'bloquea_publicar' => true, 'bloquea_login' => true],
            'ban' => ['nivel' => 4, 'fin' => null, 'bloquea_comentar' => true, 'bloquea_publicar' => true, 'bloquea_login' => true],
        };
    }
}
