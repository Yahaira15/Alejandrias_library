<?php

namespace App\Services\Gamification;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class XpService
{
    public function __construct(private GamificationService $gamification)
    {
    }

    public function award(Usuario $usuario, string $accion, ?Model $origen = null, array $metadata = [])
    {
        return $this->gamification->award($usuario, $accion, $origen, $metadata);
    }

    public function dailyAccess(Usuario $usuario): array
    {
        return $this->gamification->recordDailyAccess($usuario);
    }

    public function track(Usuario $usuario, string $accion, int $amount = 1): void
    {
        $this->gamification->trackAction($usuario, $accion, $amount);
    }
}
