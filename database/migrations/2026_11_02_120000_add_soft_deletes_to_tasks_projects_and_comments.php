<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tasks, projects and task comments are deleted softly: a "deleted" row keeps all of its data and
     * only disappears from the app's queries, so nothing is ever dropped from the database.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
