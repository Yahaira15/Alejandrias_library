<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {

            $table->enum('usuario_acepto_terminos', [
                'Acepto',
                'No acepto'
            ])
            ->nullable()
            ->after('usuario_intereses');

        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {

            $table->dropColumn('usuario_acepto_terminos');

        });
    }
};