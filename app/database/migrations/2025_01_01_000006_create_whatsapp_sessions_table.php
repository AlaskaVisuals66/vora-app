<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('instance_name')->unique();
            $table->string('display_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->enum('state', ['disconnected','qr_pending','connecting','connected','error'])->default('disconnected');
            $table->text('qr_code')->nullable();
            $table->json('webhook_events')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id','state']);
        });
    }

    public function down(): void { Schema::dropIfExists('whatsapp_sessions'); }
};
