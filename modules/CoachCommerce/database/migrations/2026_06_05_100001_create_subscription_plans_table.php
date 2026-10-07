<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 8, 2)->nullable();
            $table->decimal('price_yearly', 8, 2)->nullable();
            $table->unsignedSmallInteger('sessions_per_month')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();

            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
