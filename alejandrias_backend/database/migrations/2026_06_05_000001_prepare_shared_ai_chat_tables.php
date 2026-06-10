<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversacion_ia')) {
            Schema::create('conversacion_ia', function (Blueprint $table) {
                $table->bigIncrements('conversacion_id');
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('titulo', 160)->default('Chat IA');
                $table->string('estado', 40)->default('activa');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mensaje_ia')) {
            Schema::create('mensaje_ia', function (Blueprint $table) {
                $table->bigIncrements('mensaje_id');
                $table->unsignedBigInteger('conversacion_id')->nullable()->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('rol', 40);
                $table->text('contenido');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Conservamos el historial IA compartido. Estas tablas pueden existir
        // previamente en Supabase y no deben borrarse por rollback accidental.
    }
};
