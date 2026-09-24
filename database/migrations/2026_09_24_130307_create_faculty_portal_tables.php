<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('late_minutes')->default(15);
            $table->unsignedTinyInteger('report_days')->default(30);
            $table->timestamps();
        });

        Schema::create('portal_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->timestamp('published_at')->index();
            $table->timestamps();
        });

        Schema::create('student_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_complaints');
        Schema::dropIfExists('portal_announcements');
        Schema::dropIfExists('faculty_preferences');
    }
};
