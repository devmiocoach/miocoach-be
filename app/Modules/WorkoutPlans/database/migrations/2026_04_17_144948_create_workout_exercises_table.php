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
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order')->default(0);
            $table->unsignedTinyInteger('sets')->default(3);
            $table->string('reps')->nullable(); // es. "8-12" o "10"
            $table->string('rest_seconds')->nullable(); // es. "60" o "60-90"
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('duration_seconds')->nullable(); // per esercizi a tempo
            $table->string('distance_meters')->nullable(); // per cardio
            $table->text('notes')->nullable();
            $table->string('day_of_week')->nullable(); // monday, tuesday, ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
