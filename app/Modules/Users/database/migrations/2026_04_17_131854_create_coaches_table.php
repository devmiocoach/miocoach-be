<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('bio')->nullable();
            $table->text('description')->nullable();
            $table->string('specialization')->nullable();
            $table->json('certifications')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->json('social_links')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
