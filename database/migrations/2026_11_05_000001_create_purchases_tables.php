<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proveedores (§8.1).
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);                       // nombre o razón social
            $table->string('tax_id', 32)->nullable();          // NIT
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        // Compras (§8.2). Numeración correlativa por veterinaria (C-000001).
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();      // donde se recibe la mercadería
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('document_number', 60)->nullable();                     // nº de factura del proveedor
            $table->date('purchased_at');
            // ordered: pedido sin recibir · partial: recibido en parte · received: completo · cancelled
            $table->enum('status', ['ordered', 'partial', 'received', 'cancelled'])->default('ordered');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);                          // % sobre subtotal − descuento
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);                          // cuenta por pagar
            $table->date('due_date')->nullable();
            $table->string('invoice_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->string('closing_notes')->nullable();                            // al cerrar con diferencias
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'purchased_at']);
            $table->index(['company_id', 'balance']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);                  // pedido
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        // Recepciones (§8.3): una compra puede llegar en varias entregas.
        Schema::create('purchase_receptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->dateTime('received_at');
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_reception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_reception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('lot', 60)->nullable();
            $table->date('expires_at')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->timestamps();
        });

        // Pagos a proveedores (§8.4).
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();   // si salió de una caja
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_reception_items');
        Schema::dropIfExists('purchase_receptions');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
