<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerprint_attendance_events', function (Blueprint $table): void {
            $table->date('attendance_date')->nullable()->after('subject_id');
        });

        $seen = [];
        DB::table('fingerprint_attendance_events')->whereNotNull('subject_id')->orderBy('scanned_at')->orderBy('id')->get()
            ->each(function (object $event) use (&$seen): void {
                $date = substr((string) $event->scanned_at, 0, 10);
                $key = $event->student_id.'|'.$event->subject_id.'|'.$date;
                if (isset($seen[$key])) {
                    DB::table('fingerprint_attendance_events')->where('id', $event->id)->delete();

                    return;
                }
                $seen[$key] = true;
                DB::table('fingerprint_attendance_events')->where('id', $event->id)->update(['attendance_date' => $date]);
            });

        Schema::table('fingerprint_attendance_events', function (Blueprint $table): void {
            $table->unique(['student_id', 'subject_id', 'attendance_date'], 'attendance_student_subject_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fingerprint_attendance_events', function (Blueprint $table): void {
            $table->dropUnique('attendance_student_subject_date_unique');
            $table->dropColumn('attendance_date');
        });
    }
};
