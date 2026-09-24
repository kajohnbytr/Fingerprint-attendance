<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table): void {
            $table->string('device_id', 100)->nullable()->index();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table): void {
            $table->dropIndex(['device_id']);
            $table->dropColumn(['device_id', 'temperature', 'humidity']);
        });
    }
};
