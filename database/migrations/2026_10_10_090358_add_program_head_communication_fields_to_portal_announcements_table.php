<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_announcements', function (Blueprint $table): void {
            $table->foreignId('block_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->string('audience', 30)->default('all')->after('block_id')->index();
            $table->timestamp('expires_at')->nullable()->after('published_at')->index();
            $table->timestamp('archived_at')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('portal_announcements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('block_id');
            $table->dropColumn(['audience', 'expires_at', 'archived_at']);
        });
    }
};
