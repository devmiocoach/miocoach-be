<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedSmallInteger('reps_done')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->tinyInteger('rir_actual')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['workout_session_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_sets');
    }
};
