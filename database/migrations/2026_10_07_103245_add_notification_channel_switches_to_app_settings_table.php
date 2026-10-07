<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings → Notifications: switch in-app notifications (bell, notifications page, live push)
     * and notification emails on or off, independently.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('app_notifications_enabled')->default(true);
            $table->boolean('mail_notifications_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['app_notifications_enabled', 'mail_notifications_enabled']);
        });
    }
};
