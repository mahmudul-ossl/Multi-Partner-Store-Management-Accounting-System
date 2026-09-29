<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->date('closed_through');
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('method');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamps();
        });

        Schema::create('profit_allocation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_allocation_id')->constrained('profit_allocations');
            $table->foreignId('partner_id')->constrained('partners');
            $table->decimal('percentage', 8, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_allocation_lines');
        Schema::dropIfExists('profit_allocations');
        Schema::dropIfExists('accounting_periods');
    }
};
