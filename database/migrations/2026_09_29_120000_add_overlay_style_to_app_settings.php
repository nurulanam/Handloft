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
            $table->unsignedTinyInteger('loading_screen_opacity')->default(10)->after('loading_screen_seconds');
            $table->unsignedTinyInteger('loading_screen_blur')->default(64)->after('loading_screen_opacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['loading_screen_opacity', 'loading_screen_blur']);
        });
    }
};
