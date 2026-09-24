<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_fingerprints', function (Blueprint $table) {
            $table->id();

            // Matches the existing students.student_id value.
            $table->string('student_id', 50);

            $table->string('device_id', 100);
            $table->unsignedInteger('fingerprint_id');
            $table->timestamps();

            // One fingerprint per student in this example.
            $table->unique('student_id');

            // A sensor slot cannot belong to two students.
            $table->unique(['device_id', 'fingerprint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprints');
    }
};