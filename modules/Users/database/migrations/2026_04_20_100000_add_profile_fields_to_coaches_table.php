<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('tagline', 150)->nullable()->after('bio');
            $table->string('mode')->nullable()->after('tagline'); // online|in_person|hybrid
            $table->json('languages')->nullable()->after('specializations');
            $table->string('intro_video_url')->nullable()->after('website_url');
            $table->decimal('price_per_session', 8, 2)->nullable()->after('hourly_rate');
            $table->unsignedTinyInteger('cancellation_window_hours')->nullable()->after('price_per_session');
            $table->decimal('lat', 10, 7)->nullable()->after('city');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->boolean('is_published')->default(false)->after('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'mode', 'languages', 'intro_video_url',
                'price_per_session', 'cancellation_window_hours',
                'lat', 'lng', 'is_published',
            ]);
        });
    }
};
