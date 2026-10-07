<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['client_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
