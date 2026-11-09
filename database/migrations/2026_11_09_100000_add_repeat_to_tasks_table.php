<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('repeat')->nullable()->after('deadline');
            // When a finished repeating task goes back to To Do.
            $table->dateTime('repeat_next_at')->nullable()->after('repeat')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['repeat_next_at']);
            $table->dropColumn(['repeat', 'repeat_next_at']);
        });
    }
};
