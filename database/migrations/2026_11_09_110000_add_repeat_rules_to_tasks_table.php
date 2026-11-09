<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('repeat_every')->default(1)->after('repeat');
            $table->json('repeat_on')->nullable()->after('repeat_every'); // weekly: weekdays 0=Sun…6=Sat; monthly: [day of month]
            $table->date('repeat_until')->nullable()->after('repeat_on');
            $table->unsignedSmallInteger('repeat_max')->nullable()->after('repeat_until'); // stop after this many repeats
            $table->unsignedSmallInteger('repeat_count')->default(0)->after('repeat_max');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['repeat_every', 'repeat_on', 'repeat_until', 'repeat_max', 'repeat_count']);
        });
    }
};
