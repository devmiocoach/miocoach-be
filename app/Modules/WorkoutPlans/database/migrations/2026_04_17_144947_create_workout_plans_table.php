<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('goal')->nullable(); // strength, hypertrophy, weight_loss, endurance, flexibility
            $table->integer('duration_weeks')->nullable();
            $table->integer('sessions_per_week')->default(3);
            $table->string('difficulty')->default('intermediate');
            $table->string('status')->default('draft'); // draft, active, archived
            $table->boolean('is_template')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plans');
    }
};
