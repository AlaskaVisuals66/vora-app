<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A migration 2025_06_04_add_channel_columns ficou MARCADA como executada, mas as
 * colunas channel_type/channel_identifier não existem no banco de produção (foram
 * dropadas em algum momento). Como aquela migration não roda de novo, criar
 * ticket/cliente com channel_type quebra o inbound ("Unknown column channel_type")
 * e a conversa do cliente é perdida. Esta migration garante que as colunas existam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'channel_type')) {
                $table->string('channel_type', 50)->nullable();
            }
            if (! Schema::hasColumn('clients', 'channel_identifier')) {
                $table->string('channel_identifier', 191)->nullable();
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'channel_type')) {
                $table->string('channel_type', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Sem down de propósito — não removemos as colunas de novo.
    }
};
