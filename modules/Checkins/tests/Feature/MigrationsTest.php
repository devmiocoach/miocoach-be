<?php

namespace Modules\Checkins\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_exist_with_key_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('checkin_forms', ['coach_id', 'title', 'frequency', 'send_time', 'questions', 'is_active']));
        $this->assertTrue(Schema::hasColumns('checkin_form_client', ['checkin_form_id', 'client_id', 'assigned_at']));
        $this->assertTrue(Schema::hasColumns('checkin_responses', ['checkin_form_id', 'client_id', 'answers', 'submitted_at']));
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasColumns('checkin_dispatches', ['checkin_form_id', 'client_id', 'period_key', 'dispatched_at']));
        $this->assertTrue(Schema::hasColumn('clients', 'timezone'));
    }
}
