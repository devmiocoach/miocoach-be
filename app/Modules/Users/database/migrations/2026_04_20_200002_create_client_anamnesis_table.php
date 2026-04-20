<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_anamnesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->text('content_encrypted');
            $table->string('iv', 64);
            $table->string('tag', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_anamnesis');
    }
};
