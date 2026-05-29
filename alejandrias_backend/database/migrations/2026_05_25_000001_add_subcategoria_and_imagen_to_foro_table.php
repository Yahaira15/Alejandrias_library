<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('foro')) {
            return;
        }

        Schema::table('foro', function (Blueprint $table) {
            if (!Schema::hasColumn('foro', 'subcategoria_id')) {
                $table->unsignedBigInteger('subcategoria_id')->nullable();
            }

            if (!Schema::hasColumn('foro', 'foro_imagen')) {
                $table->string('foro_imagen')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('foro')) {
            return;
        }

        Schema::table('foro', function (Blueprint $table) {
            if (Schema::hasColumn('foro', 'subcategoria_id')) {
                $table->dropColumn('subcategoria_id');
            }

            if (Schema::hasColumn('foro', 'foro_imagen')) {
                $table->dropColumn('foro_imagen');
            }
        });
    }
};
