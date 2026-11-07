<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jaulas, caniles y demás espacios de internación.
        Schema::create('kennels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        // Internación (§10.1, §10.2, §10.5).
        Schema::create('hospitalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kennel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'discharged', 'deceased', 'cancelled'])->default('active');
            $table->enum('condition', ['stable', 'observation', 'improving', 'critical'])->default('observation');
            $table->dateTime('admitted_at');
            $table->string('reason');
            $table->string('admission_diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);

            // Alta médica.
            $table->dateTime('discharged_at')->nullable();
            $table->string('final_diagnosis')->nullable();
            $table->text('home_treatment')->nullable();
            $table->text('discharge_instructions')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['pet_id', 'status']);
            $table->index(['kennel_id', 'status']);
        });

        // Tratamientos programados (§10.3).
        Schema::create('hospitalization_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medication');
            $table->string('dose', 60);
            $table->decimal('stock_quantity', 12, 2)->nullable();   // unidades que salen del inventario por toma
            $table->string('route', 20);
            $table->unsignedSmallInteger('frequency_hours');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('instructions')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cada toma del horario de administración.
        Schema::create('hospitalization_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_treatment_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'given', 'skipped', 'cancelled'])->default('pending');
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'scheduled_at']);
        });

        // Cuidados, signos vitales, evolución e incidencias, por profesional (§10.3, §10.4).
        Schema::create('hospitalization_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                 // ver HospitalizationRecord::TYPES
            $table->dateTime('recorded_at');
            $table->text('notes')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('hydration', 20)->nullable();
            $table->string('condition', 20)->nullable();                // cambio de estado del paciente
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospitalization_id', 'recorded_at']);
        });

        // Costos asociados además de la estadía (§10.5).
        Schema::create('hospitalization_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalization_charges');
        Schema::dropIfExists('hospitalization_records');
        Schema::dropIfExists('hospitalization_doses');
        Schema::dropIfExists('hospitalization_treatments');
        Schema::dropIfExists('hospitalizations');
        Schema::dropIfExists('kennels');
    }
};
