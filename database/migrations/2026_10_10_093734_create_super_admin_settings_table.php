<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('school_name')->default('University Attendance Portal');
            $table->unsignedTinyInteger('attendance_required')->default(75);
            $table->boolean('proxy_detection')->default(true);
            $table->boolean('require_admin_mfa')->default(false);
            $table->unsignedSmallInteger('session_timeout_minutes')->default(30);
            $table->unsignedSmallInteger('password_expiry_days')->default(90);
            $table->boolean('low_attendance_alerts')->default(true);
            $table->boolean('system_health_reports')->default(true);
            $table->timestamps();
        });

        DB::table('super_admin_settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_settings');
    }
};
