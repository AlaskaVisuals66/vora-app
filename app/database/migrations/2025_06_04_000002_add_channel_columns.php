<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'channel_type')) {
                $table->string('channel_type', 50)->nullable()->after('whatsapp_jid');
            }
            if (!Schema::hasColumn('clients', 'channel_identifier')) {
                $table->string('channel_identifier', 191)->nullable()->after('channel_type');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'channel_type')) {
                $table->string('channel_type', 50)->nullable()->after('channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['channel_type', 'channel_identifier']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('channel_type');
        });
    }
};
