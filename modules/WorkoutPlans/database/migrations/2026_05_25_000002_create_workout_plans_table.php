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

            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal')->nullable();
            $table->string('difficulty')->nullable();

            $table->unsignedTinyInteger('duration_weeks')->nullable();
            $table->unsignedTinyInteger('sessions_per_week')->nullable();

            $table->boolean('is_template')->default(false);
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'is_active']);
            $table->index(['client_id', 'is_active']);
            $table->index('is_template');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plans');
    }
};
