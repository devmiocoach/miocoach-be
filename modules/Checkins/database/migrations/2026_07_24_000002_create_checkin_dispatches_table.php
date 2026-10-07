<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkin_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('period_key');
            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->unique(['checkin_form_id', 'client_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_dispatches');
    }
};
