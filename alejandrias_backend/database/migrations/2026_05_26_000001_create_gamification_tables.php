<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('insignia')) {
            Schema::create('insignia', function (Blueprint $table) {
                $table->id('insignia_id');
                $table->string('insignia_slug', 80)->unique();
                $table->string('insignia_ruta', 30);
                $table->unsignedTinyInteger('insignia_nivel');
                $table->string('insignia_nombre', 120);
                $table->string('insignia_emoji', 12);
                $table->text('insignia_descripcion');
                $table->text('insignia_requisito');
                $table->json('insignia_criterios');
                $table->string('insignia_color', 20)->default('#B88A44');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('usuario_insignia')) {
            Schema::create('usuario_insignia', function (Blueprint $table) {
                $table->id('usuario_insignia_id');
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->foreignId('insignia_id')->constrained('insignia', 'insignia_id')->cascadeOnDelete();
                $table->timestamp('obtenida_en')->useCurrent();
                $table->json('snapshot_metricas')->nullable();
                $table->unique(['usuario_id', 'insignia_id']);
            });
        }

        if (!Schema::hasTable('xp_evento')) {
            Schema::create('xp_evento', function (Blueprint $table) {
                $table->id('xp_evento_id');
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->string('accion', 60);
                $table->integer('xp');
                $table->string('origen_tipo', 80)->nullable();
                $table->unsignedBigInteger('origen_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('creado_en')->useCurrent();
                $table->index(['usuario_id', 'accion', 'creado_en']);
                $table->unique(['usuario_id', 'accion', 'origen_tipo', 'origen_id'], 'xp_evento_unico_origen');
            });
        }

        if (!Schema::hasTable('usuario_progreso')) {
            Schema::create('usuario_progreso', function (Blueprint $table) {
                $table->id('usuario_progreso_id');
                $table->foreignId('usuario_id')->unique()->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->string('ruta_principal', 30)->default('explorador');
                $table->integer('xp_total')->default(0);
                $table->unsignedTinyInteger('nivel_lider')->default(0);
                $table->unsignedTinyInteger('nivel_explorador')->default(0);
                $table->json('metricas')->nullable();
                $table->timestamp('actualizado_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_progreso');
        Schema::dropIfExists('xp_evento');
        Schema::dropIfExists('usuario_insignia');
        Schema::dropIfExists('insignia');
    }
};
