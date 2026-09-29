<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_journals', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('reference')->unique();
            $table->string('description');
            $table->decimal('amount', 18, 2);
            $table->string('status');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::create('manual_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_journal_id')->constrained('manual_journals');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts');
            $table->decimal('debit', 18, 2);
            $table->decimal('credit', 18, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_financial_account_id')->constrained('financial_accounts');
            $table->foreignId('to_financial_account_id')->constrained('financial_accounts');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->string('reference')->unique();
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('manual_journal_lines');
        Schema::dropIfExists('manual_journals');
    }
};
