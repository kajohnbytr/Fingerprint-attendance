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
        Schema::create('class_grace_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('late_minutes')->default(10);
            $table->timestamps();

            $table->unique(['user_id', 'subject_id', 'block_id']);
        });

        Schema::create('archive_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('student_name');
            $table->string('student_block', 50);
            $table->text('reason');
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_requests');
        Schema::dropIfExists('class_grace_periods');
    }
};
