<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('service_type', 50);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('cancellation_token', 100)->unique()->nullable();
            $table->timestamp('cancellation_token_expires_at')->nullable();
            $table->string('google_calendar_event_id', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'starts_at']);
            $table->index(['client_id', 'starts_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
