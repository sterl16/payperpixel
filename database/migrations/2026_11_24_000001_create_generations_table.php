<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // money is stored in nanodollars (1 USD = 1,000,000,000)
    // pixelforge gives costs to 9 decimals so this keeps them exact
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // sent as the Idempotency-Key so a retry never makes a second generation
            $table->uuid('idempotency_key')->unique();
            $table->string('pixelforge_id')->nullable()->unique();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images')->nullable();

            // pending means pixelforge hasn't accepted it yet
            $table->string('status')->default('pending')->index();

            $table->json('rates')->nullable();
            $table->json('usage')->nullable();

            $table->unsignedBigInteger('estimated_charge_nanos');
            $table->unsignedBigInteger('provider_cost_nanos')->nullable();
            $table->unsignedBigInteger('charge_nanos')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedSmallInteger('submit_attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
