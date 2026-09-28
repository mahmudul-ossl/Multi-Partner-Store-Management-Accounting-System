<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('payment_method');
            $table->foreignId('financial_account_id')->constrained('financial_accounts');
            $table->string('reference')->unique();
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('reason');
            $table->string('payment_method');
            $table->foreignId('financial_account_id')->constrained('financial_accounts');
            $table->string('reference')->unique();
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_partner_id')->constrained('partners');
            $table->foreignId('to_partner_id')->constrained('partners');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('reference')->unique();
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['from_partner_id', 'status']);
            $table->index(['to_partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transfers');
        Schema::dropIfExists('partner_withdrawals');
        Schema::dropIfExists('partner_investments');
    }
};
