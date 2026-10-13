<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings → Work schedule: the work timer (auto time log) on or off, and how long a forgotten
     * timer may run before it's stopped automatically (null = no limit).
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('work_timer_enabled')->default(true);
            $table->unsignedTinyInteger('work_timer_max_hours')->nullable()->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['work_timer_enabled', 'work_timer_max_hours']);
        });
    }
};
