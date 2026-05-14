<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id','action']);
            $table->index(['subject_type','subject_id']);
        });

        Schema::create('online_status', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->enum('status', ['online','away','busy','offline'])->default('offline');
            $table->ipAddress('ip_address')->nullable();
            $table->string('socket_id')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->integer('tickets_opened')->default(0);
            $table->integer('tickets_closed')->default(0);
            $table->integer('messages_inbound')->default(0);
            $table->integer('messages_outbound')->default(0);
            $table->integer('avg_first_response_seconds')->nullable();
            $table->integer('avg_resolution_seconds')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','sector_id','user_id','date'], 'analytics_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('online_status');
        Schema::dropIfExists('audit_logs');
    }
};
