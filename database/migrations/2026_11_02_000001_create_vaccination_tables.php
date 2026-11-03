<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de vacunas de la veterinaria, con su protocolo (§5.1).
        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('manufacturer', 120)->nullable();
            $table->string('presentation', 120)->nullable();
            $table->text('description')->nullable();
            // Protocolo: serie inicial de N dosis separadas por X días; luego
            // revacunación cada Y días (null = no se revacuna).
            $table->unsignedTinyInteger('doses_in_series')->default(1);
            $table->unsignedSmallInteger('series_interval_days')->nullable();
            $table->unsignedSmallInteger('booster_interval_days')->nullable();
            $table->unsignedSmallInteger('min_age_weeks')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // A qué especies aplica cada vacuna (la antirrábica, a varias).
        Schema::create('species_vaccine', function (Blueprint $table) {
            $table->foreignId('vaccine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->primary(['vaccine_id', 'species_id']);
        });

        // Aplicaciones de vacuna (§5.2).
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('vaccine_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('applied_at');
            $table->unsignedTinyInteger('dose_number')->default(1);
            $table->string('lot', 60)->nullable();
            $table->date('lot_expires_at')->nullable();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'vaccine_id', 'applied_at']);
            $table->index(['company_id', 'next_due_at']);
        });

        // Desparasitaciones internas y externas (§5.4).
        Schema::create('dewormings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['internal', 'external', 'both']);
            $table->string('product', 160);          // el vínculo con inventario llega con §7
            $table->string('dose', 60)->nullable();
            $table->decimal('weight', 6, 2)->nullable(); // peso al aplicar: la dosis suele depender de él
            $table->date('applied_at');
            $table->date('next_due_at')->nullable();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'type', 'applied_at']);
            $table->index(['company_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dewormings');
        Schema::dropIfExists('vaccinations');
        Schema::dropIfExists('species_vaccine');
        Schema::dropIfExists('vaccines');
    }
};
