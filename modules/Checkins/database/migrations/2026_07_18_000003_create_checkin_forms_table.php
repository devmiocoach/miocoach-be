<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->time('send_time');
            $table->json('questions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['coach_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_forms');
    }
};
