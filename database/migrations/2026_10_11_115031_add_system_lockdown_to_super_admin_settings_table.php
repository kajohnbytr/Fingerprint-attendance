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
        Schema::table('super_admin_settings', function (Blueprint $table): void {
            $table->boolean('system_lockdown')->default(false)->after('require_admin_mfa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('super_admin_settings', function (Blueprint $table): void {
            $table->dropColumn('system_lockdown');
        });
    }
};
