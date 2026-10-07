<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'timezone')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Europe/Rome')->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients', 'timezone')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
