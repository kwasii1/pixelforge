<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Pixelforge's evt_ id. Unique, so a redelivered event can never be stored or acted on twice.
            $table->string('event_id')->unique();
            $table->string('type');
            $table->string('pixelforge_generation_id')->nullable()->index();
            $table->json('payload');
            $table->unsignedSmallInteger('delivery_attempt')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
