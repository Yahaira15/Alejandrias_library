<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('moderacion_ia')) {
            Schema::table('moderacion_ia', function (Blueprint $table) {
                $this->addModeracionIaColumns($table, true);
            });
        } else {
            Schema::create('moderacion_ia', function (Blueprint $table) {
                $table->bigIncrements('moderacion_id');
                $this->addModeracionIaColumns($table, false);
            });
        }

        $this->addContentModerationColumns('publicacion');
        $this->addContentModerationColumns('comentario');
    }

    public function down(): void
    {
        $this->dropContentModerationColumns('publicacion');
        $this->dropContentModerationColumns('comentario');
    }

    private function addModeracionIaColumns(Blueprint $table, bool $tableExists): void
    {
        $columns = [
            'publicacion_id' => fn () => $table->unsignedBigInteger('publicacion_id')->nullable(),
            'foro_id' => fn () => $table->unsignedBigInteger('foro_id')->nullable(),
            'usuario_id' => fn () => $table->unsignedBigInteger('usuario_id')->nullable(),
            'contenido_analizado' => fn () => $table->text('contenido_analizado')->nullable(),
            'categoria_detectada' => fn () => $table->string('categoria_detectada', 80)->default('otro'),
            'tipo_riesgo' => fn () => $table->string('tipo_riesgo', 80)->default('ninguno'),
            'estado' => fn () => $table->string('estado', 20)->default('revision'),
            'riesgo' => fn () => $table->decimal('riesgo', 4, 2)->default(0.50),
            'razon' => fn () => $table->text('razon')->nullable(),
            'modelo_ia' => fn () => $table->string('modelo_ia', 80)->nullable(),
            'procesado' => fn () => $table->boolean('procesado')->default(true),
            'revisado' => fn () => $table->boolean('revisado')->default(false),
            'revisado_por' => fn () => $table->unsignedBigInteger('revisado_por')->nullable(),
            'decision_admin' => fn () => $table->string('decision_admin', 80)->nullable(),
            'created_at' => fn () => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!$tableExists || !Schema::hasColumn('moderacion_ia', $column)) {
                $definition();
            }
        }
    }

    private function addContentModerationColumns(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $columns = [
                'estado_moderacion' => fn () => $table->string('estado_moderacion', 20)->default('visible'),
                'ia_riesgo' => fn () => $table->decimal('ia_riesgo', 4, 2)->nullable(),
                'ia_razon' => fn () => $table->text('ia_razon')->nullable(),
                'ia_fecha_analisis' => fn () => $table->timestamp('ia_fecha_analisis')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (!Schema::hasColumn($tableName, $column)) {
                    $definition();
                }
            }
        });
    }

    private function dropContentModerationColumns(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $columns = [];

            foreach (['estado_moderacion', 'ia_riesgo', 'ia_razon', 'ia_fecha_analisis'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
