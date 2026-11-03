<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Métodos de pago configurables (§9.3). `type` dice cómo se comporta:
        // solo el efectivo cuenta para el arqueo de caja.
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->enum('type', ['cash', 'transfer', 'qr', 'card', 'other']);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Turno de caja (§9.4): apertura con saldo inicial y cierre con arqueo.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();   // lo que debería haber en efectivo
            $table->decimal('counted_cash', 12, 2)->nullable();    // lo que se contó
            $table->decimal('difference', 12, 2)->nullable();      // contado − esperado
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['caja_id', 'status']);
        });

        // Todo lo que entra o sale de un turno de caja, por método de pago.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['sale', 'receivable', 'income', 'expense', 'withdrawal', 'refund']);
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);                      // + entra, − sale
            $table->string('description');
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Ventas (§9.1–9.2). Numeración correlativa por veterinaria.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);        // descuento global (además del de cada línea)
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('refunded', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);         // pendiente de cobro (cuenta por cobrar)
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'balance']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['product', 'service', 'consultation', 'other']);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('returned_quantity', 12, 2)->default(0);
            // Falso cuando el producto ya salió del stock antes (insumo o
            // medicamento dispensado en la consulta): la venta solo lo cobra.
            $table->boolean('affects_stock')->default(false);
            $table->timestamps();
        });

        // Pagos de una venta: al venderla y abonos posteriores (§9.3, §9.5).
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['sale', 'installment'])->default('sale');
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        // Devoluciones parciales (§9.2).
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);                      // lo devuelto al cliente
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // Gastos (§9.6).
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('concept');
            $table->decimal('amount', 12, 2);
            $table->date('spent_at');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();  // pagado desde la caja
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();          // responsable
            $table->string('receipt')->nullable();                                             // comprobante adjunto
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'spent_at']);
        });

        // Una consulta se cobra una sola vez.
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('appointment_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('payment_methods');
    }
};
