<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('moderacion_ia')) {
            Schema::create('moderacion_ia', function (Blueprint $table) {
                $table->bigIncrements('moderacion_id');
                $table->unsignedBigInteger('publicacion_id')->nullable();
                $table->unsignedBigInteger('foro_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('contenido_analizado');
                $table->string('categoria_detectada', 80)->default('otro');
                $table->string('tipo_riesgo', 80)->default('ninguno');
                $table->string('estado', 20)->default('revision');
                $table->decimal('riesgo', 4, 2)->default(0.50);
                $table->text('razon')->nullable();
                $table->string('modelo_ia', 80)->nullable();
                $table->boolean('procesado')->default(true);
                $table->boolean('revisado')->default(false);
                $table->unsignedBigInteger('revisado_por')->nullable();
                $table->string('decision_admin', 80)->nullable();
                $table->timestamps();
            });
        }

        $this->addModerationColumns('publicacion');
        $this->addModerationColumns('comentario');
    }

    public function down(): void
    {
        $this->dropModerationColumns('publicacion');
        $this->dropModerationColumns('comentario');
    }

    private function addModerationColumns(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'estado_moderacion')) {
                $table->string('estado_moderacion', 20)->default('visible');
            }

            if (!Schema::hasColumn($tableName, 'ia_riesgo')) {
                $table->decimal('ia_riesgo', 4, 2)->nullable();
            }

            if (!Schema::hasColumn($tableName, 'ia_razon')) {
                $table->text('ia_razon')->nullable();
            }

            if (!Schema::hasColumn($tableName, 'ia_fecha_analisis')) {
                $table->timestamp('ia_fecha_analisis')->nullable();
            }
        });
    }

    private function dropModerationColumns(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $columns = [];

            if (Schema::hasColumn($tableName, 'estado_moderacion')) {
                $columns[] = 'estado_moderacion';
            }

            if (Schema::hasColumn($tableName, 'ia_riesgo')) {
                $columns[] = 'ia_riesgo';
            }

            if (Schema::hasColumn($tableName, 'ia_razon')) {
                $columns[] = 'ia_razon';
            }

            if (Schema::hasColumn($tableName, 'ia_fecha_analisis')) {
                $columns[] = 'ia_fecha_analisis';
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
