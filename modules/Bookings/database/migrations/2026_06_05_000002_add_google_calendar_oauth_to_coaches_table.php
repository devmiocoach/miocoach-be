<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->text('google_calendar_access_token')->nullable()->after('stripe_onboarding_completed');
            $table->text('google_calendar_refresh_token')->nullable()->after('google_calendar_access_token');
            $table->timestamp('google_calendar_token_expires_at')->nullable()->after('google_calendar_refresh_token');
            $table->string('google_calendar_id', 255)->nullable()->after('google_calendar_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_access_token',
                'google_calendar_refresh_token',
                'google_calendar_token_expires_at',
                'google_calendar_id',
            ]);
        });
    }
};
