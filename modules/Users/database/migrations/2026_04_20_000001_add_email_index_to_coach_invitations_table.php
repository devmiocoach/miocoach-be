<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_invitations', function (Blueprint $table) {
            $table->index(['coach_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('coach_invitations', function (Blueprint $table) {
            $table->dropIndex(['coach_id', 'email']);
        });
    }
};
