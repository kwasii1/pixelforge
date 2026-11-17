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
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();

            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');

            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('submission_attempts')->default(0);

            // Money is in nanodollars (1 USD = 1,000,000,000). Estimates are fixed at submission.
            $table->unsignedBigInteger('estimated_min_cost');
            $table->unsignedBigInteger('estimated_max_cost');
            $table->unsignedBigInteger('estimated_min_price');
            $table->unsignedBigInteger('estimated_max_price');
            $table->unsignedBigInteger('provider_cost')->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->json('usage')->nullable();
            $table->json('rates')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
