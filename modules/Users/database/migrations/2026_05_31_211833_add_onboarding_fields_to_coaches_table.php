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
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('slug');
            $table->enum('session_duration_default', ['45', '60', '90'])->default('60')->after('cancellation_window_hours');
            $table->unsignedInteger('single_session_price_cents')->nullable()->after('price_per_session');
            $table->unsignedInteger('monthly_subscription_cents')->nullable()->after('single_session_price_cents');
            $table->unsignedInteger('quarterly_subscription_cents')->nullable()->after('monthly_subscription_cents');
            $table->timestamp('onboarding_completed_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'session_duration_default',
                'single_session_price_cents',
                'monthly_subscription_cents',
                'quarterly_subscription_cents',
                'onboarding_completed_at',
            ]);
        });
    }
};
