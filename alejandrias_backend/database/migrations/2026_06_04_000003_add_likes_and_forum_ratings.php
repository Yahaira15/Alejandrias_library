<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publicacion') && !Schema::hasColumn('publicacion', 'publicacion_likes')) {
            Schema::table('publicacion', function (Blueprint $table) {
                $table->unsignedInteger('publicacion_likes')->default(0);
            });
        }

        if (Schema::hasTable('comentario') && !Schema::hasColumn('comentario', 'comentario_likes')) {
            Schema::table('comentario', function (Blueprint $table) {
                $table->unsignedInteger('comentario_likes')->default(0);
            });
        }

        if (!Schema::hasTable('publicacion_like')) {
            Schema::create('publicacion_like', function (Blueprint $table) {
                $table->id('publicacion_like_id');
                $table->foreignId('publicacion_id')->constrained('publicacion', 'publicacion_id')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->timestamp('fecha_creacion')->nullable();
                $table->unique(['publicacion_id', 'usuario_id'], 'publicacion_like_unique');
            });
        }

        if (!Schema::hasTable('comentario_like')) {
            Schema::create('comentario_like', function (Blueprint $table) {
                $table->id('comentario_like_id');
                $table->foreignId('comentario_id')->constrained('comentario', 'comentario_id')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->timestamp('fecha_creacion')->nullable();
                $table->unique(['comentario_id', 'usuario_id'], 'comentario_like_unique');
            });
        }

        if (!Schema::hasTable('puntuacion_foro')) {
            Schema::create('puntuacion_foro', function (Blueprint $table) {
                $table->id('puntuacion_foro_id');
                $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
                $table->foreignId('foro_id')->constrained('foro', 'foro_id')->cascadeOnDelete();
                $table->decimal('puntuacion', 2, 1);
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();
                $table->unique(['usuario_id', 'foro_id'], 'puntuacion_foro_usuario_unique');
                $table->index(['foro_id', 'puntuacion'], 'puntuacion_foro_promedio_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('puntuacion_foro');
        Schema::dropIfExists('comentario_like');
        Schema::dropIfExists('publicacion_like');

        if (Schema::hasTable('comentario') && Schema::hasColumn('comentario', 'comentario_likes')) {
            Schema::table('comentario', function (Blueprint $table) {
                $table->dropColumn('comentario_likes');
            });
        }

        if (Schema::hasTable('publicacion') && Schema::hasColumn('publicacion', 'publicacion_likes')) {
            Schema::table('publicacion', function (Blueprint $table) {
                $table->dropColumn('publicacion_likes');
            });
        }
    }
};
