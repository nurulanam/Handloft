<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Work schedule. Days use Carbon's numbering: 0 = Sunday … 6 = Saturday.
            $table->json('off_days')->nullable();
            $table->unsignedTinyInteger('week_starts_on')->default(1);
            $table->decimal('daily_hours_target', 4, 2)->nullable()->default(8);

            // Appearance.
            $table->string('ui_style')->default('glass');
            $table->string('theme')->default('forest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['off_days', 'week_starts_on', 'daily_hours_target', 'ui_style', 'theme']);
        });
    }
};
