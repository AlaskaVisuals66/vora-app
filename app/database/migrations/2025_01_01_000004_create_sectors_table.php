<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('menu_key', 8)->nullable();
            $table->string('color', 7)->default('#3478f6');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->json('working_hours')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id','slug']);
            $table->index(['tenant_id','parent_id','active']);
        });

        Schema::create('attendant_sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(1);
            $table->timestamps();
            $table->unique(['user_id','sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendant_sectors');
        Schema::dropIfExists('sectors');
    }
};
