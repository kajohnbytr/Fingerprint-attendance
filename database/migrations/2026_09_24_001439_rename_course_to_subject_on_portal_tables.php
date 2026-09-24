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
        foreach (['blocks', 'students'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('course', 'subject');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['blocks', 'students'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('subject', 'course');
            });
        }
    }
};
