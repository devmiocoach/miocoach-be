<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rescue assignments that only ever existed as workout_plans.client_id.
        // DB::table (not Eloquent) so soft-deleted plans are included too.
        DB::table('workout_plans')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    DB::table('plan_assignments')->updateOrInsert(
                        ['workout_plan_id' => $plan->id, 'client_id' => $plan->client_id],
                        [
                            'assigned_at' => $plan->created_at ?? now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });

        // Legacy `reps` is superseded by `reps_target`. Preserve any value first.
        DB::table('workout_exercises')
            ->whereNull('reps_target')
            ->whereNotNull('reps')
            ->update(['reps_target' => DB::raw('reps')]);

        // The legacy composite index still references `order`; it must go before the column does.
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropIndex(['workout_plan_id', 'week_number', 'day_number', 'order']);
        });

        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropColumn(['order', 'reps']);
        });

        // Likewise, the `client_id` composite index must go before the column does.
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'is_active']);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });

        // cascadeOnDelete silently shortened every plan using a deleted exercise.
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropForeign(['exercise_id']);
            $table->foreign('exercise_id')->references('id')->on('exercises')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropForeign(['exercise_id']);
            $table->foreign('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedSmallInteger('reps')->nullable();
        });

        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->index(['workout_plan_id', 'week_number', 'day_number', 'order']);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->index(['client_id', 'is_active']);
        });
    }
};
