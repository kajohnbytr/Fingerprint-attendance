<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->string('device_id', 100);
            $table->unsignedInteger('fingerprint_id');

            // Use Laravel server time rather than trusting the device clock.
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['student_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_attendance_events');
    }
};