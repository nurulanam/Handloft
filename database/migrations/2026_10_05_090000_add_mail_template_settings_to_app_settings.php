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
            $table->string('mail_brand_color')->nullable()->after('mail_from_name');
            $table->string('mail_button_text_color')->nullable()->after('mail_brand_color');
            $table->string('mail_footer_note')->nullable()->after('mail_button_text_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['mail_brand_color', 'mail_button_text_color', 'mail_footer_note']);
        });
    }
};
