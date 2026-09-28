<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('reference')->unique();
            $table->nullableMorphs('source');
            $table->string('description');
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('reversal_of')->nullable()->constrained('journal_entries');
            $table->timestamps();

            $table->index(['entry_date', 'status']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts');
            $table->decimal('debit', 18, 2);
            $table->decimal('credit', 18, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'chart_of_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
