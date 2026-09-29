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
        Schema::create('master_schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('block', 50);
            $table->string('course_name');
            $table->string('schedule');
            $table->string('room', 100)->nullable();
            $table->string('assigned_professor_email')->nullable();
            $table->unsignedSmallInteger('grace_override_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('schedule_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('student_name');
            $table->string('block', 50);
            $table->string('course_name');
            $table->string('current_schedule');
            $table->string('current_room', 100)->nullable();
            $table->string('requested_schedule');
            $table->string('requested_room', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('details');
            $table->string('status', 20)->default('Success');
            $table->timestamps();
        });
        Schema::create('app_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category', 50);
            $table->text('description');
            $table->string('severity', 20)->default('Normal');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_reports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('schedule_change_requests');
        Schema::dropIfExists('master_schedule_entries');
    }
};
