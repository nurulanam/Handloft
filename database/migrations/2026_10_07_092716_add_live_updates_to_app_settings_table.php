<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings → Live notifications: turn the Reverb push on or off, and optionally point it at a
     * Reverb server other than the one in .env.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // null = follow .env (on when BROADCAST_CONNECTION is "reverb").
            $table->boolean('live_updates_enabled')->nullable();
            // How often the bell checks for new notifications when live push is off or disconnected.
            $table->unsignedSmallInteger('live_poll_seconds')->default(30);

            // Custom Reverb app (blank = use .env). The secret is stored encrypted.
            $table->string('reverb_app_id')->nullable();
            $table->string('reverb_app_key')->nullable();
            $table->text('reverb_app_secret')->nullable();
            // Where the app server reaches Reverb…
            $table->string('reverb_host')->nullable();
            $table->unsignedInteger('reverb_port')->nullable();
            $table->string('reverb_scheme')->nullable();
            // …and where browsers connect (often the public domain in front of it).
            $table->string('reverb_client_host')->nullable();
            $table->unsignedInteger('reverb_client_port')->nullable();
            $table->string('reverb_client_scheme')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'live_updates_enabled', 'live_poll_seconds',
                'reverb_app_id', 'reverb_app_key', 'reverb_app_secret',
                'reverb_host', 'reverb_port', 'reverb_scheme',
                'reverb_client_host', 'reverb_client_port', 'reverb_client_scheme',
            ]);
        });
    }
};
