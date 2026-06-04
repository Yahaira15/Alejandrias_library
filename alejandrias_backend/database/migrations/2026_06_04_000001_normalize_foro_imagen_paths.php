<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('foro') || !Schema::hasColumn('foro', 'foro_imagen')) {
            return;
        }

        DB::table('foro')
            ->whereNotNull('foro_imagen')
            ->orderBy('foro_id')
            ->select(['foro_id', 'foro_imagen'])
            ->chunkById(100, function ($foros) {
                foreach ($foros as $foro) {
                    $ruta = $this->normalizarRutaImagen($foro->foro_imagen);

                    if ($ruta !== $foro->foro_imagen) {
                        DB::table('foro')
                            ->where('foro_id', $foro->foro_id)
                            ->update(['foro_imagen' => $ruta]);
                    }
                }
            }, 'foro_id');
    }

    public function down(): void
    {
        //
    }

    private function normalizarRutaImagen(?string $imagen): ?string
    {
        $imagen = trim((string) $imagen);

        if ($imagen === '') {
            return null;
        }

        if (preg_match('/^(data:|blob:)/i', $imagen)) {
            return $imagen;
        }

        if (preg_match('/^https?:\/\//i', $imagen)) {
            $path = parse_url($imagen, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/storage/')) {
                return $path;
            }

            return $imagen;
        }

        if (str_starts_with($imagen, '/storage/')) {
            return $imagen;
        }

        $imagen = ltrim($imagen, '/');

        return str_starts_with($imagen, 'storage/')
            ? '/' . $imagen
            : '/storage/' . $imagen;
    }
};
