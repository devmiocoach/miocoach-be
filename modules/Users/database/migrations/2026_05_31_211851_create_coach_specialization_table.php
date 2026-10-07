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
        Schema::create('coach_specialization', function (Blueprint $table) {
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->primary(['coach_id', 'specialization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_specialization');
    }
};
