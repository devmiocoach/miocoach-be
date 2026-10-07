<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->boolean('accepts_free_consultation')->default(false)->after('free_first_session');
            $table->boolean('accepts_direct_message')->default(false)->after('accepts_free_consultation');
            // true by default so already-published coaches keep today's booking CTA
            $table->boolean('accepts_direct_booking')->default(true)->after('accepts_direct_message');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn(['accepts_free_consultation', 'accepts_direct_message', 'accepts_direct_booking']);
        });
    }
};
