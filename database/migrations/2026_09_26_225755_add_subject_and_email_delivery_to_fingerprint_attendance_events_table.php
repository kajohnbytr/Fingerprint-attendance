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
        Schema::table('fingerprint_attendance_events', function (Blueprint $table) {
            $table->foreignId('subject_id')
                ->nullable()
                ->after('fingerprint_id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('email_sent_at')->nullable()->after('scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fingerprint_attendance_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn('email_sent_at');
        });
    }
};
