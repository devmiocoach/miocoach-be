<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_packages', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedSmallInteger('validity_days')->default(90)->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('coach_packages', function (Blueprint $table) {
            $table->dropColumn(['description', 'validity_days']);
        });
    }
};
