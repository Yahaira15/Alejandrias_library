<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sancion')) {
            return;
        }

        Schema::create('sancion', function (Blueprint $table) {
            $table->id('sancion_id');
            $table->foreignId('sancion_usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
            $table->string('sancion_tipo', 50);
            $table->unsignedTinyInteger('sancion_nivel')->default(1);
            $table->text('sancion_motivo');
            $table->timestamp('sancion_fecha_inicio')->useCurrent();
            $table->timestamp('sancion_fecha_fin')->nullable();
            $table->boolean('sancion_activa')->default(true);
            $table->foreignId('sancion_admin_id')->nullable()->constrained('usuario', 'usuario_id')->nullOnDelete();
            $table->foreignId('sancion_reporte_id')->nullable()->constrained('reporte', 'reporte_id')->nullOnDelete();
            $table->boolean('bloquea_comentar')->default(false);
            $table->boolean('bloquea_publicar')->default(false);
            $table->boolean('bloquea_login')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sancion');
    }
};
