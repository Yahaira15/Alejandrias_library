<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('comentario')) {
            return;
        }

        Schema::table('comentario', function (Blueprint $table) {
            if (!Schema::hasColumn('comentario', 'comentario_padre_id')) {
                $table->unsignedBigInteger('comentario_padre_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('comentario') || !Schema::hasColumn('comentario', 'comentario_padre_id')) {
            return;
        }

        Schema::table('comentario', function (Blueprint $table) {
            $table->dropColumn('comentario_padre_id');
        });
    }
};
