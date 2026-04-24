<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();

            // Profilo pubblico
            $table->string('bio')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->json('specializations')->nullable();   // ['crossfit', 'riabilitazione', ...]
            $table->json('certifications')->nullable();    // [{ name, issuer, year }, ...]
            $table->json('social_links')->nullable();      // { instagram, facebook, youtube, ... }
            $table->unsignedTinyInteger('years_of_experience')->nullable();

            // Contatti e sede
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 2)->nullable();    // sigla provincia (es. MI, RM)

            // Tariffe e capacità
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->unsignedSmallInteger('max_clients')->nullable(); // limite clienti gestibili

            // Dati fiscali (Italia)
            $table->string('ragione_sociale')->nullable();
            $table->string('p_iva', 11)->nullable();
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('tax_regime')->nullable();     // forfettario | ordinario | semplificato
            $table->string('sdi_code', 7)->nullable();    // codice destinatario fattura elettronica
            $table->string('pec')->nullable();

            // Integrazione pagamenti
            $table->string('stripe_connect_id')->nullable()->unique(); // Stripe Connect account ID
            $table->boolean('stripe_onboarding_completed')->default(false);

            // Visibilità e stato
            $table->boolean('is_verified')->default(false);  // verifica manuale credenziali
            $table->boolean('is_visible')->default(true);    // visibile nel marketplace

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
