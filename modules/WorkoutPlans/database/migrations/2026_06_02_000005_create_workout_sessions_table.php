<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('workout_plan_id')->nullable()->constrained('workout_plans')->nullOnDelete();
            $table->foreignId('workout_day_id')->nullable()->constrained('workout_days')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total_volume_kg', 10, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'started_at']);
            $table->index('workout_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
