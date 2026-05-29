<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Links a contact (client) to each WhatsApp number (session/instance) it
        // belongs to. Many-to-many: the same phone can be a contact on more than
        // one of the tenant's numbers, so we don't lose it under a single instance.
        Schema::create('client_whatsapp_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained('whatsapp_sessions')->cascadeOnDelete();
            // The contact's name as seen on THIS instance (WhatsApp pushName).
            $table->string('name_on_instance')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'whatsapp_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_whatsapp_session');
    }
};
