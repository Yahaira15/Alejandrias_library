<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte', function (Blueprint $table) {
            if (!Schema::hasColumn('reporte', 'reporte_tipo')) {
                $table->string('reporte_tipo', 50)->nullable();
            }

            if (!Schema::hasColumn('reporte', 'reporte_referencia_id')) {
                $table->integer('reporte_referencia_id')->nullable();
            }

            if (!Schema::hasColumn('reporte', 'reporte_usuario_reporta_id')) {
                $table->integer('reporte_usuario_reporta_id')->nullable();
            }

            if (!Schema::hasColumn('reporte', 'reporte_descripcion')) {
                $table->text('reporte_descripcion')->nullable();
            }

            if (!Schema::hasColumn('reporte', 'reporte_prioridad')) {
                $table->string('reporte_prioridad', 20)->default('media');
            }

            if (!Schema::hasColumn('reporte', 'ia_detectado')) {
                $table->boolean('ia_detectado')->default(false);
            }
        });

        if (Schema::hasColumn('reporte', 'reporte_publicacion_id')) {
            DB::table('reporte')
                ->whereNull('reporte_tipo')
                ->whereNotNull('reporte_publicacion_id')
                ->update(['reporte_tipo' => 'publicacion']);

            DB::table('reporte')
                ->whereNull('reporte_referencia_id')
                ->whereNotNull('reporte_publicacion_id')
                ->update(['reporte_referencia_id' => DB::raw('reporte_publicacion_id')]);
        }

        if (Schema::hasColumn('reporte', 'reporte_usuario_id')) {
            DB::table('reporte')
                ->whereNull('reporte_usuario_reporta_id')
                ->whereNotNull('reporte_usuario_id')
                ->update(['reporte_usuario_reporta_id' => DB::raw('reporte_usuario_id')]);
        }

        DB::table('reporte')->whereNull('reporte_tipo')->update(['reporte_tipo' => 'publicacion']);
        DB::table('reporte')->whereNull('reporte_prioridad')->update(['reporte_prioridad' => 'media']);
        DB::table('reporte')->whereNull('ia_detectado')->update(['ia_detectado' => false]);
    }

    public function down(): void
    {
        Schema::table('reporte', function (Blueprint $table) {
            foreach ([
                'reporte_tipo',
                'reporte_referencia_id',
                'reporte_usuario_reporta_id',
                'reporte_descripcion',
                'reporte_prioridad',
                'ia_detectado',
            ] as $column) {
                if (Schema::hasColumn('reporte', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
