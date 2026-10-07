<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Day-grouped exercises link to a plan through their WorkoutDay, so a flat
     * workout_plan_id is no longer mandatory on every WorkoutExercise row.
     */
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->foreignId('workout_plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->foreignId('workout_plan_id')->nullable(false)->change();
        });
    }
};
