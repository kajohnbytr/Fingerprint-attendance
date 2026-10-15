<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_head_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('school_year', 9)->nullable();
            $table->string('semester', 30)->nullable();
            $table->unsignedTinyInteger('report_days')->default(7);
            $table->boolean('notify_announcements')->default(true);
            $table->boolean('notify_complaints')->default(true);
            $table->boolean('notify_schedule_changes')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_head_preferences');
    }
};
