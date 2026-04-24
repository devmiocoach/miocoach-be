<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('goals');
            $table->string('phone', 30)->nullable()->after('tags');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->timestamp('subscription_expires_at')->nullable()->after('avatar_url');
            $table->unsignedSmallInteger('subscription_sessions_remaining')->nullable()->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['tags', 'phone', 'avatar_url', 'subscription_expires_at', 'subscription_sessions_remaining']);
        });
    }
};
