<?php

namespace Modules\Checkins\Tests\Feature;

use Tests\TestCase;

class ModuleSmokeTest extends TestCase
{
    public function test_module_is_enabled(): void
    {
        $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);
        $this->assertTrue($statuses['Checkins'] ?? false);
    }
}
