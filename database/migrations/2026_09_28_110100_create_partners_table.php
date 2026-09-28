<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code', 32)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();
            $table->date('joining_date')->index();
            $table->decimal('ownership_percentage', 8, 4);
            $table->decimal('investment_percentage', 8, 4);
            $table->enum('status', ['active', 'inactive', 'suspended'])->index();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
