<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('large_discount_threshold', 18, 2);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('transaction_date');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('delivery', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('due_amount', 18, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts');
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('cogs_amount', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('transaction_date');
            $table->decimal('total', 18, 2);
            $table->decimal('cogs_total', 18, 2);
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns');
            $table->foreignId('sale_item_id')->constrained('sale_items');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_total', 18, 2);
            $table->decimal('cogs_amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('financial_account_id')->constrained('financial_accounts');
            $table->string('payment_method');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::create('sales_cancellations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->date('transaction_date');
            $table->string('reason');
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('financial_account_id')->constrained('financial_accounts');
            $table->string('payment_method');
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained('customers');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sales_cancellations');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('sales_settings');
        Schema::dropIfExists('customers');
    }
};
