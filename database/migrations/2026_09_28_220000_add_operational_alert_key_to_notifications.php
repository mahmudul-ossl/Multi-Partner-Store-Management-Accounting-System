<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('alert_key', 64)->nullable();
            $table->unique(['notifiable_type', 'notifiable_id', 'alert_key'], 'notifications_operational_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_operational_unique');
            $table->dropColumn('alert_key');
        });
    }
};
