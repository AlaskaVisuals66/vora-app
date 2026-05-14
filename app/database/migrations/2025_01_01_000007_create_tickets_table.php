<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('protocol')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('whatsapp_session_id')->nullable()->constrained('whatsapp_sessions')->nullOnDelete();
            $table->enum('status', ['menu','queued','open','pending','resolved','closed'])->default('menu');
            $table->enum('priority', ['low','normal','high','urgent'])->default('normal');
            $table->string('channel')->default('whatsapp');
            $table->string('subject')->nullable();
            $table->json('menu_state')->nullable();
            $table->integer('first_response_seconds')->nullable();
            $table->integer('resolution_seconds')->nullable();
            $table->integer('messages_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id','status']);
            $table->index(['tenant_id','sector_id','status']);
            $table->index(['tenant_id','assigned_to','status']);
            $table->index(['tenant_id','client_id']);
        });

        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('to_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#888888');
            $table->timestamps();
            $table->unique(['tenant_id','name']);
        });

        Schema::create('ticket_tag_pivot', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['ticket_id','ticket_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_tag_pivot');
        Schema::dropIfExists('ticket_tags');
        Schema::dropIfExists('ticket_transfers');
        Schema::dropIfExists('tickets');
    }
};
