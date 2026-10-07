<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->string('metric_type', 30);
            $table->decimal('value', 8, 2);
            $table->timestamp('measured_at');
            $table->string('notes', 1000)->nullable();
            $table->string('photo_storage_key')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'metric_type', 'measured_at']);
            $table->index(['coach_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
