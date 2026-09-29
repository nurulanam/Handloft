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
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('logged_date');
            $table->decimal('hours', 4, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            // One entry per person per day — logging again for the same day
            // updates that day's entry instead of adding a second one.
            $table->unique(['task_id', 'user_id', 'logged_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
    }
};
