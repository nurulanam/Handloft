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
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_id')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('department')->nullable()->after('status');
            $table->date('joining_date')->nullable()->after('department');
            $table->string('profile_photo')->nullable()->after('joining_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'phone', 'department', 'joining_date', 'profile_photo']);
        });
    }
};
