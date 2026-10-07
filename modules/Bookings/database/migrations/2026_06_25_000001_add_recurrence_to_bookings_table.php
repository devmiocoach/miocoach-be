<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('recurrence_rule')->nullable()->after('notes');
            $table->unsignedBigInteger('recurrence_parent_id')->nullable()->after('recurrence_rule');
            $table->foreign('recurrence_parent_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn(['recurrence_rule', 'recurrence_parent_id']);
        });
    }
};
