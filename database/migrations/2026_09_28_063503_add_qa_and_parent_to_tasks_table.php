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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('qa_id')->nullable()->after('task_category_id')->constrained('users')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->after('qa_id')->constrained('tasks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qa_id');
            $table->dropConstrainedForeignId('parent_task_id');
        });
    }
};
