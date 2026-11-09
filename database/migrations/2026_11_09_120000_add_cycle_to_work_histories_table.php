<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_histories', function (Blueprint $table) {
            // Which run of a repeating task this record belongs to (0 = the first), so each run keeps its own hours.
            $table->unsignedSmallInteger('cycle')->default(0)->after('task_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_histories', function (Blueprint $table) {
            $table->dropColumn('cycle');
        });
    }
};
