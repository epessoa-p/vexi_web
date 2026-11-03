<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Citas y lista de espera: un paciente sin cita que llega a la
        // veterinaria se registra como cita "walk-in" directamente en espera.
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('veterinarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();                // motivo de consulta
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'waiting', 'in_progress', 'attended', 'cancelled', 'no_show'])
                  ->default('pending');
            $table->boolean('is_walk_in')->default(false);

            // Trazabilidad de cada cambio de estado.
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();       // llegó: entra a la sala de espera
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'starts_at']);
            $table->index(['veterinarian_id', 'starts_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
