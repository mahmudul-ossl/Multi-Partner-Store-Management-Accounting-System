<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['status', 'transaction_date']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->index(['status', 'transaction_date']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->index(['status', 'transaction_date']);
        });

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->index(['chart_of_account_id', 'journal_entry_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('moved_on');
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'transaction_date']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex(['status', 'transaction_date']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['status', 'transaction_date']);
        });

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropIndex(['chart_of_account_id', 'journal_entry_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['moved_on']);
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropIndex(['requested_at']);
        });
    }
};
