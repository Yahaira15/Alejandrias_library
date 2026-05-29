<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categoria') && !Schema::hasColumn('categoria', 'categoria_imagen')) {
            Schema::table('categoria', function (Blueprint $table) {
                $table->string('categoria_imagen')->nullable();
            });
        }

        foreach (['subcategoria', 'subcategorias'] as $tabla) {
            if (Schema::hasTable($tabla) && !Schema::hasColumn($tabla, 'subcategoria_imagen')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->string('subcategoria_imagen')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categoria') && Schema::hasColumn('categoria', 'categoria_imagen')) {
            Schema::table('categoria', function (Blueprint $table) {
                $table->dropColumn('categoria_imagen');
            });
        }

        foreach (['subcategoria', 'subcategorias'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'subcategoria_imagen')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('subcategoria_imagen');
                });
            }
        }
    }
};
