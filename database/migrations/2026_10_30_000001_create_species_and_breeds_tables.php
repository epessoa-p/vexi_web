<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo por empresa: cada veterinaria arranca con uno base
        // (App\Support\ClinicalCatalog) y lo adapta a su realidad.
        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['species_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
        Schema::dropIfExists('species');
    }
};
