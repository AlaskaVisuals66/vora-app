<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de mensagens não lidas por conversa (bolinha laranja na lista, estilo
 * WhatsApp). Incrementa a cada mensagem recebida do cliente e zera quando o
 * atendente abre a conversa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'unread_count')) {
                $table->unsignedInteger('unread_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        // sem down
    }
};
