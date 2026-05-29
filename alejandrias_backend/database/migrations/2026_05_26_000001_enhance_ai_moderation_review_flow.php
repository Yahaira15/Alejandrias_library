<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('moderacion_ia')) {
            return;
        }

        Schema::table('moderacion_ia', function (Blueprint $table) {
            if (!Schema::hasColumn('moderacion_ia', 'comentario_id')) {
                $table->unsignedBigInteger('comentario_id')->nullable();
            }

            if (!Schema::hasColumn('moderacion_ia', 'safety_ratings')) {
                $table->json('safety_ratings')->nullable();
            }

            if (!Schema::hasColumn('moderacion_ia', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (!Schema::hasColumn('moderacion_ia', 'moderado_por')) {
                $table->unsignedBigInteger('moderado_por')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('moderacion_ia')) {
            return;
        }

        Schema::table('moderacion_ia', function (Blueprint $table) {
            $columns = [];

            foreach (['comentario_id', 'safety_ratings', 'metadata', 'moderado_por'] as $column) {
                if (Schema::hasColumn('moderacion_ia', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
