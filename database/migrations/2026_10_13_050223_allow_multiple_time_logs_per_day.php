<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A person can log several entries on the same task and day (each work session, or a second manual
     * entry) instead of one entry per day that a second log overwrote.
     */
    public function up(): void
    {
        Schema::table('task_time_logs', function (Blueprint $table) {
            // Added first: on MySQL the task_id foreign key uses the unique index until another covers it.
            $table->index(['task_id', 'user_id', 'logged_date']);

            if (! Schema::hasIndex('task_time_logs', ['user_id', 'logged_date'])) {
                $table->index(['user_id', 'logged_date']);
            }
        });

        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->dropUnique(['task_id', 'user_id', 'logged_date']);
        });
    }

    public function down(): void
    {
        // Back to one entry per person, task and day: merge same-day entries into the oldest one.
        $duplicates = DB::table('task_time_logs')
            ->selectRaw('task_id, user_id, date(logged_date) as day, min(id) as keep_id, sum(hours) as total')
            ->groupBy('task_id', 'user_id', DB::raw('date(logged_date)'))
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('task_time_logs')->where('id', $row->keep_id)->update(['hours' => min((float) $row->total, 24)]);
            DB::table('task_time_logs')
                ->where('task_id', $row->task_id)->where('user_id', $row->user_id)
                ->whereDate('logged_date', $row->day)->where('id', '!=', $row->keep_id)
                ->delete();
        }

        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->unique(['task_id', 'user_id', 'logged_date']);
        });

        // (user_id, logged_date) stays: MySQL now uses it for the user_id foreign key.
        Schema::table('task_time_logs', function (Blueprint $table) {
            $table->dropIndex(['task_id', 'user_id', 'logged_date']);
        });
    }
};
