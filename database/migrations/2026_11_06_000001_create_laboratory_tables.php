<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de estudio (§6.2), configurables por veterinaria.
        Schema::create('lab_study_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 30);                  // ver LabStudyType::CATEGORIES
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('lab_study_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_study_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('unit', 30)->nullable();
            $table->decimal('reference_min', 12, 3)->nullable();
            $table->decimal('reference_max', 12, 3)->nullable();
            $table->string('reference_text', 120)->nullable(); // referencia no numérica: "Negativo"
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Solicitudes (§6.1) y su seguimiento (§6.4).
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lab_study_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at');
            $table->enum('status', ['requested', 'in_process', 'completed', 'delivered', 'cancelled'])->default('requested');
            $table->string('external_lab', 120)->nullable();  // si se deriva a un laboratorio externo
            $table->text('notes')->nullable();

            // Resultado (§6.3).
            $table->date('result_date')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('result_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['pet_id', 'requested_at']);
        });

        // Valores obtenidos. Copian nombre, unidad y referencia del catálogo:
        // editarlo después no debe alterar un informe ya emitido.
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('value', 120)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('reference_min', 12, 3)->nullable();
            $table->decimal('reference_max', 12, 3)->nullable();
            $table->string('reference_text', 120)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Documentos e imágenes. Van en el disco privado: se sirven solo por
        // una ruta con sesión, permiso y empresa activa.
        Schema::create('lab_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_attachments');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('lab_study_parameters');
        Schema::dropIfExists('lab_study_types');
    }
};
