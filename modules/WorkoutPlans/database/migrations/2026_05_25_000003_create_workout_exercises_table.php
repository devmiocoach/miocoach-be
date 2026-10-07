<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workout_plan_id')->constrained('workout_plans')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();

            // Organisation within the plan
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->unsignedTinyInteger('day_number')->nullable();
            $table->unsignedSmallInteger('order')->default(0);

            // Prescription
            $table->unsignedTinyInteger('sets')->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['workout_plan_id', 'week_number', 'day_number', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
