<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkin_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['checkin_form_id', 'client_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_responses');
    }
};
