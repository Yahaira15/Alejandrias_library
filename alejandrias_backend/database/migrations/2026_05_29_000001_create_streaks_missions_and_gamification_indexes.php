<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('racha_usuario')) {
            Schema::create('racha_usuario', function (Blueprint $table) {
                $table->id('racha_usuario_id');
                $table->foreignId('usuario_id')->unique()->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->unsignedInteger('dias_consecutivos')->default(0);
                $table->date('ultima_fecha')->nullable();
                $table->unsignedInteger('mejor_racha')->default(0);
                $table->boolean('recompensa_reclamada')->default(false);
                $table->integer('xp_obtenida_hoy')->default(0);
                $table->timestamp('actualizado_en')->nullable();
                $table->index(['ultima_fecha', 'dias_consecutivos']);
            });
        }

        if (!Schema::hasTable('misiones_diarias')) {
            Schema::create('misiones_diarias', function (Blueprint $table) {
                $table->id('mision_diaria_id');
                $table->string('mision_slug', 80);
                $table->string('mision_titulo', 140);
                $table->text('mision_descripcion');
                $table->string('mision_tipo', 60);
                $table->unsignedSmallInteger('objetivo')->default(1);
                $table->integer('xp_recompensa')->default(0);
                $table->integer('puntos_recompensa')->default(0);
                $table->string('insignia_temporal', 80)->nullable();
                $table->date('fecha');
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->unique(['mision_slug', 'fecha']);
                $table->index(['fecha', 'activa']);
            });
        }

        if (!Schema::hasTable('usuario_mision')) {
            Schema::create('usuario_mision', function (Blueprint $table) {
                $table->id('usuario_mision_id');
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->foreignId('mision_diaria_id')->constrained('misiones_diarias', 'mision_diaria_id')->cascadeOnDelete();
                $table->unsignedSmallInteger('progreso')->default(0);
                $table->boolean('completada')->default(false);
                $table->boolean('reclamada')->default(false);
                $table->timestamp('completada_en')->nullable();
                $table->timestamp('reclamada_en')->nullable();
                $table->timestamps();
                $table->unique(['usuario_id', 'mision_diaria_id']);
                $table->index(['usuario_id', 'completada', 'reclamada']);
            });
        }

        Schema::table('xp_evento', function (Blueprint $table) {
            $table->index(['accion', 'creado_en'], 'xp_evento_accion_fecha_idx');
        });

        Schema::table('publicacion', function (Blueprint $table) {
            $table->index(['publicacion_usuario_id', 'publicacion_fecha_creacion'], 'publicacion_usuario_fecha_idx');
            $table->index(['publicacion_foro_id', 'publicacion_usuario_id'], 'publicacion_foro_usuario_idx');
        });

        Schema::table('comentario', function (Blueprint $table) {
            $table->index(['comentario_usuario_id', 'comentario_id'], 'comentario_usuario_id_idx');
            $table->index(['comentario_publicacion_id', 'comentario_usuario_id'], 'comentario_publicacion_usuario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_mision');
        Schema::dropIfExists('misiones_diarias');
        Schema::dropIfExists('racha_usuario');
    }
};
