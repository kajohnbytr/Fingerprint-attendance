<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->foreignId('student_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('school_year', 9)->nullable();
            $table->string('semester', 30)->nullable();
            $table->unsignedTinyInteger('late_minutes')->default(15);
            $table->unsignedTinyInteger('report_days')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn('is_active');
        });
    }
};
