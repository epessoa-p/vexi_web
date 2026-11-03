<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        // Productos y medicamentos (§7.1). Recurso limitado por plan (max_products).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->nullable();          // código interno
            $table->string('barcode', 60)->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('presentation', 120)->nullable();
            $table->string('unit', 20)->default('unidad');   // ver Product::UNITS
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('track_batches')->default(false); // exige lote y vencimiento al ingresar
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'barcode']);
        });

        // Existencia actual por sucursal. Es un caché de la suma de movimientos,
        // que se actualiza en la misma transacción que cada movimiento.
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
        });

        // Lotes con vencimiento (§7.4), por sucursal.
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('lot', 60);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('initial_quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'branch_id', 'lot']);
            $table->index(['company_id', 'expires_at']);
        });

        // Kardex (§7.3): cada movimiento es inmutable; un error se corrige con
        // un movimiento inverso, nunca editando o borrando.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer_in', 'transfer_out']);
            $table->string('reason', 30);                    // ver StockMovement::REASONS
            $table->decimal('quantity', 12, 2);              // con signo: + entra, − sale
            $table->decimal('balance_after', 12, 2);         // existencia de la sucursal tras el movimiento
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->nullableMorphs('source');                // consulta, vacuna, receta… que lo originó
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'id']);
            $table->index(['company_id', 'created_at']);
        });

        // Farmacia clínica (§7.5): vínculos opcionales con el inventario.
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('consultation_id')->constrained()->nullOnDelete();
            $table->decimal('dispensed_quantity', 12, 2)->nullable()->after('sort');
            $table->timestamp('dispensed_at')->nullable()->after('dispensed_quantity');
        });

        // Insumos utilizados en la consulta y sus procedimientos: se descuentan al finalizarla.
        Schema::create('consultation_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('vaccines', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('dewormings', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('consultation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dewormings', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
        Schema::table('vaccines', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
        Schema::dropIfExists('consultation_supplies');
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['dispensed_quantity', 'dispensed_at']);
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
