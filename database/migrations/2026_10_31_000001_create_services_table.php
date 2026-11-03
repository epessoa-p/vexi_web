<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de servicios de la veterinaria: lo usan la agenda (motivo
        // y duración de la cita) y más adelante el POS (precio).
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 30);                       // ver Service::CATEGORIES
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('color', 7)->nullable();              // color en el calendario
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
