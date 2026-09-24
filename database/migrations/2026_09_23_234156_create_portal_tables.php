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
            $table->string('role', 20)->default('faculty');
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('course', 100);
            $table->unsignedTinyInteger('year_level');
            $table->string('adviser', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 30)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->string('course', 100);
            $table->unsignedTinyInteger('year_level');
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->string('subject_code', 30);
            $table->string('subject_name', 120);
            $table->string('day', 10);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50);
            $table->string('instructor', 120);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('students');
        Schema::dropIfExists('blocks');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
