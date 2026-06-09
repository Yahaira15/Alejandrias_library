<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publicacion') && !Schema::hasColumn('publicacion', 'publicacion_adjuntos')) {
            Schema::table('publicacion', function (Blueprint $table) {
                $table->json('publicacion_adjuntos')->nullable()->after('publicacion_contenido');
            });
        }

        if (Schema::hasTable('comentario') && !Schema::hasColumn('comentario', 'comentario_adjuntos')) {
            Schema::table('comentario', function (Blueprint $table) {
                $table->json('comentario_adjuntos')->nullable()->after('comentario_contenido');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comentario') && Schema::hasColumn('comentario', 'comentario_adjuntos')) {
            Schema::table('comentario', function (Blueprint $table) {
                $table->dropColumn('comentario_adjuntos');
            });
        }

        if (Schema::hasTable('publicacion') && Schema::hasColumn('publicacion', 'publicacion_adjuntos')) {
            Schema::table('publicacion', function (Blueprint $table) {
                $table->dropColumn('publicacion_adjuntos');
            });
        }
    }
};
