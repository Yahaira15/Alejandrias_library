<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('foro') || !Schema::hasColumn('foro', 'foro_imagen')) {
            return;
        }

        $this->expandirColumnaImagen();
        $this->convertirImagenesExistentes();
    }

    public function down(): void
    {
        //
    }

    private function expandirColumnaImagen(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE foro ALTER COLUMN foro_imagen TYPE TEXT');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE foro MODIFY foro_imagen LONGTEXT NULL');
        }
    }

    private function convertirImagenesExistentes(): void
    {
        DB::table('foro')
            ->whereNotNull('foro_imagen')
            ->orderBy('foro_id')
            ->select(['foro_id', 'foro_imagen'])
            ->chunkById(50, function ($foros) {
                foreach ($foros as $foro) {
                    $dataUrl = $this->dataUrlDesdeStorage($foro->foro_imagen);

                    if ($dataUrl) {
                        DB::table('foro')
                            ->where('foro_id', $foro->foro_id)
                            ->update(['foro_imagen' => $dataUrl]);
                    }
                }
            }, 'foro_id');
    }

    private function dataUrlDesdeStorage(?string $imagen): ?string
    {
        $imagen = trim((string) $imagen);

        if ($imagen === '' || preg_match('/^(data:|blob:|https?:\/\/)/i', $imagen)) {
            return null;
        }

        $path = preg_replace('#^/?storage/#', '', ltrim($imagen, '/'));

        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
        $bytes = Storage::disk('public')->get($path);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
};
