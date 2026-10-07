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

            // Ownership — null = global platform exercise, non-null = coach-specific
            $table->foreignId('coach_id')->nullable()->constrained('coaches')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();

            $table->string('category');
            $table->string('difficulty');

            // Targeting
            $table->json('muscle_groups')->nullable();
            $table->json('equipment')->nullable();

            // Media
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();

            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'is_active']);
            $table->index('category');
            $table->index('difficulty');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
