<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('source', 32)->index();
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date')->index();
            $table->string('payment_method', 32);
            $table->foreignId('financial_account_id')->constrained();
            $table->text('note')->nullable();
            $table->string('status', 32)->index();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->change();
        });

        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });

        Schema::dropIfExists('sales_incomes');
    }
};
