<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Datos profesionales del veterinario (§12.3): salen en recetas e informes.
    // Son de la persona, no de la empresa, por eso viven en users.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('professional_license', 60)->nullable()->after('phone');
            $table->string('specialty', 120)->nullable()->after('professional_license');
            $table->string('signature')->nullable()->after('specialty');   // imagen en el disco public
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['professional_license', 'specialty', 'signature']);
        });
    }
};
