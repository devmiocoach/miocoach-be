<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->string('metric_type', 30);
            $table->decimal('target_value', 8, 2);
            $table->date('target_date')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'coach_id', 'metric_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_goals');
    }
};
