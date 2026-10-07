<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->foreignId('workout_day_id')->nullable()->constrained('workout_days')->nullOnDelete();
            $table->unsignedSmallInteger('reps_target')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->unsignedTinyInteger('rir_target')->nullable();
            // order_index: position within a WorkoutDay (replaces flat `order` for day-grouped exercises)
            $table->unsignedSmallInteger('order_index')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropForeign(['workout_day_id']);
            $table->dropColumn(['workout_day_id', 'reps_target', 'weight_kg', 'rir_target', 'order_index']);
        });
    }
};
