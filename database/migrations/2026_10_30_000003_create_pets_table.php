<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mascotas = pacientes. Cada una tiene su propio expediente clínico;
        // los módulos clínicos (consultas, vacunas…) colgarán de pet_id.
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained('breeds')->nullOnDelete();
            $table->string('name', 80);
            $table->enum('sex', ['male', 'female', 'unknown'])->default('unknown');
            $table->boolean('sterilized')->default(false);
            $table->date('birth_date')->nullable();
            $table->decimal('weight', 6, 2)->nullable();          // último peso, en kg
            $table->string('color', 80)->nullable();
            $table->enum('size', ['mini', 'small', 'medium', 'large', 'giant'])->nullable();
            $table->string('microchip', 30)->nullable();
            $table->string('identification', 30)->nullable();    // nº de identificación / tatuaje / placa
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'inactive', 'deceased'])->default('active');
            $table->text('notes')->nullable();

            // Ficha del paciente: antecedentes que se leen en cada atención.
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('previous_surgeries')->nullable();
            $table->text('usual_medications')->nullable();
            $table->text('clinical_alerts')->nullable();         // se muestra destacado en la ficha

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'microchip']);
        });

        Schema::create('pet_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 6, 2);
            $table->date('recorded_at');
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_weights');
        Schema::dropIfExists('pets');
    }
};
