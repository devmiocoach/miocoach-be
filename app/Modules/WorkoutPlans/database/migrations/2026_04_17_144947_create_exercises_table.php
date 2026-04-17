<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('muscle_group'); // chest, back, legs, shoulders, arms, core, full_body
            $table->string('equipment')->nullable(); // barbell, dumbbell, bodyweight, machine, cable
            $table->string('difficulty')->default('intermediate'); // beginner, intermediate, advanced
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_public')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
