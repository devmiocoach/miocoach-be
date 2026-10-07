<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->string('storage_key');
            $table->string('thumbnail_key')->nullable();
            $table->string('pose', 30)->nullable();
            $table->date('taken_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['client_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
