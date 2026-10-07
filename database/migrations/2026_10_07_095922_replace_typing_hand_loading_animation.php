<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "Typing Hand" loading animation was retired; anyone using it gets "Handoff" instead.
     */
    public function up(): void
    {
        DB::table('app_settings')->where('loading_screen_style', 'hand')->update(['loading_screen_style' => 'handoff']);
    }

    public function down(): void
    {
        // Nothing to restore: the old animation no longer exists.
    }
};
