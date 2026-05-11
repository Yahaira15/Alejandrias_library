<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('foro', 'foro_password')) {
            return;
        }

        Schema::table('foro', function (Blueprint $table) {
            $table->string('foro_password')->nullable()->after('foro_privado');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('foro', 'foro_password')) {
            return;
        }

        Schema::table('foro', function (Blueprint $table) {
            $table->dropColumn('foro_password');
        });
    }
};
