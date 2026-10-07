<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            // Sede
            $table->unsignedSmallInteger('service_radius_km')->nullable()->after('lng');
            $table->boolean('available_at_home')->default(false)->after('service_radius_km');
            $table->json('partner_gyms')->nullable()->after('available_at_home');

            // Gallery
            $table->json('gallery_photos')->nullable()->after('cover_photo_url');
            $table->string('intro_video_url')->nullable()->change();
            $table->string('intro_video_storage_path')->nullable()->after('intro_video_url');

            // Policy
            $table->unsignedInteger('no_show_fee_cents')->nullable()->after('quarterly_subscription_cents');
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable()->after('no_show_fee_cents');
            $table->boolean('free_first_session')->default(false)->after('late_tolerance_minutes');
            $table->boolean('welcome_discount_enabled')->default(false)->after('free_first_session');
            $table->unsignedSmallInteger('welcome_discount_percent')->nullable()->after('welcome_discount_enabled');
            $table->text('custom_terms')->nullable()->after('welcome_discount_percent');

            // Certificazioni extra fields (P.IVA + insurance already exist partially)
            $table->boolean('has_insurance')->default(false)->after('p_iva');
            $table->string('insurance_company')->nullable()->after('has_insurance');
            $table->date('insurance_expiry')->nullable()->after('insurance_company');
            $table->string('insurance_file_url')->nullable()->after('insurance_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn([
                'service_radius_km',
                'available_at_home',
                'partner_gyms',
                'gallery_photos',
                'intro_video_storage_path',
                'no_show_fee_cents',
                'late_tolerance_minutes',
                'free_first_session',
                'welcome_discount_enabled',
                'welcome_discount_percent',
                'custom_terms',
                'has_insurance',
                'insurance_company',
                'insurance_expiry',
                'insurance_file_url',
            ]);
        });
    }
};
