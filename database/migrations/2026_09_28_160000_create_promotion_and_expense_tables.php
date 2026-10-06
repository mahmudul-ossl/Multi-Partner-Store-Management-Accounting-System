<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('budget', 18, 2);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->string('status');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('promotion_partner_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('promotion_id')->constrained('promotions');
            $table->foreignId('partner_id')->constrained('partners');
            $table->decimal('amount', 18, 2);
            $table->string('funded_by');
            $table->string('payment_method')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts');
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('category');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('payment_method')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->text('description');
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('promotion_partner_expenses');
        Schema::dropIfExists('promotions');
    }
};
