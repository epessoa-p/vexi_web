<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consulta veterinaria: evaluación clínica, tratamiento e indicaciones.
        // Una vez finalizada no se edita (trazabilidad); solo admite seguimientos.
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['in_progress', 'finished'])->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();

            // Evaluación clínica (§4.2)
            $table->string('reason')->nullable();
            $table->text('anamnesis')->nullable();
            $table->text('physical_exam')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();          // °C
            $table->decimal('weight', 6, 2)->nullable();               // kg
            $table->unsignedSmallInteger('heart_rate')->nullable();    // lpm
            $table->unsignedSmallInteger('respiratory_rate')->nullable(); // rpm
            $table->string('hydration', 20)->nullable();
            $table->unsignedTinyInteger('body_condition')->nullable(); // escala 1–9 (WSAVA)
            $table->string('mucous_membranes', 20)->nullable();
            $table->text('clinical_notes')->nullable();

            // Tratamiento y seguimiento (§4.4, §4.7)
            $table->text('instructions')->nullable();      // indicaciones al propietario (van en la receta)
            $table->text('recommendations')->nullable();
            $table->date('next_review_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'started_at']);
            $table->index(['company_id', 'status']);
            $table->index(['pet_id', 'started_at']);
        });

        Schema::create('consultation_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['primary', 'secondary', 'differential'])->default('primary');
            $table->string('code', 20)->nullable();        // clasificación propia de la veterinaria
            $table->string('description');
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'description']);
        });

        // Receta: medicamentos indicados (§4.4–4.5). El vínculo con productos
        // del inventario llega con el módulo de farmacia.
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('medication');
            $table->string('presentation')->nullable();
            $table->string('dose', 60);
            $table->string('route', 20);
            $table->string('frequency', 60);
            $table->string('duration', 60)->nullable();
            $table->string('quantity', 40)->nullable();
            $table->string('instructions')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('consultation_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('materials')->nullable();
            $table->text('medications')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Evolución posterior a la consulta (§4.7).
        Schema::create('consultation_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->dateTime('recorded_at');
            $table->enum('treatment_status', ['in_course', 'improving', 'unchanged', 'worsening', 'resolved'])->default('in_course');
            $table->text('notes');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_followups');
        Schema::dropIfExists('consultation_procedures');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('consultation_diagnoses');
        Schema::dropIfExists('consultations');
    }
};
