<?php
// database/migrations/2026_05_14_000001_add_ai_settings_to_sectors.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->json('ai_settings')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('ai_settings');
        });
    }
};
