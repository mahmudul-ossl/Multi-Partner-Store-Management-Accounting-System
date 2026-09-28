<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('request_type');
            $table->decimal('min_amount', 18, 2);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->unsignedSmallInteger('required_approvals');
            $table->timestamps();

            $table->index(['request_type', 'min_amount']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type');
            $table->nullableMorphs('reference');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status');
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->unsignedSmallInteger('required_approvals');
            $table->unsignedSmallInteger('completed_approvals')->default(0);
            $table->decimal('amount', 18, 2);
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'request_type']);
            $table->index('requested_by');
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['approval_request_id', 'user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_thresholds');
    }
};
