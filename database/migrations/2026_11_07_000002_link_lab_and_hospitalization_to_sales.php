<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estudios e internaciones se cobran en el punto de venta, una sola vez, igual
 * que las consultas (`sale_id` vacío = pendiente de cobro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_study_types', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->after('description');
        });

        Schema::table('lab_requests', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('consultation_id')->constrained()->nullOnDelete();
        });

        Schema::table('hospitalizations', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('consultation_id')->constrained()->nullOnDelete();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->enum('type', ['product', 'service', 'consultation', 'lab', 'hospitalization', 'other'])->change();
            $table->foreignId('lab_request_id')->nullable()->after('consultation_id')->constrained()->nullOnDelete();
            $table->foreignId('hospitalization_id')->nullable()->after('lab_request_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('sale_items')->whereIn('type', ['lab', 'hospitalization'])->update(['type' => 'other']);

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospitalization_id');
            $table->dropConstrainedForeignId('lab_request_id');
            $table->enum('type', ['product', 'service', 'consultation', 'other'])->change();
        });
        Schema::table('hospitalizations', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
        Schema::table('lab_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
        Schema::table('lab_study_types', fn (Blueprint $table) => $table->dropColumn('price'));
    }
};
