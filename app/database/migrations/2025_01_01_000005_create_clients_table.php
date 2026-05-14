<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 32);
            $table->string('whatsapp_jid')->nullable();
            $table->string('email')->nullable();
            $table->string('document')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('tags')->nullable();
            $table->json('attributes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','phone']);
            $table->index(['tenant_id','last_message_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('clients'); }
};
