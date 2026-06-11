<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foro_favorito', function (Blueprint $table) {
            $table->id('foro_favorito_id');
            $table->foreignId('usuario_id')->constrained('usuario', 'usuario_id')->cascadeOnDelete();
            $table->foreignId('foro_id')->constrained('foro', 'foro_id')->cascadeOnDelete();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->unique(['usuario_id', 'foro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foro_favorito');
    }
};
