<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabella pivot per la relazione many-to-many tra Coach e Atleta (Client).
     * Un coach può avere molti atleti; un atleta può essere seguito da più coach.
     */
    public function up(): void
    {
        Schema::create('athlete_coach', function (Blueprint $table) {
            $table->id();

            $table->foreignId('athlete_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->foreignId('coach_id')
                ->constrained('coaches')
                ->cascadeOnDelete();

            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->unique(['athlete_id', 'coach_id']);

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athlete_coach');
    }
};
