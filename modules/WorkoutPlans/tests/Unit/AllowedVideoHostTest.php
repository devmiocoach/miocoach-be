<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Rules\AllowedVideoHost;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AllowedVideoHostTest extends TestCase
{
    /** @param array<int, string> $hosts */
    private function passes(string $url): bool
    {
        return Validator::make(
            ['videoUrl' => $url],
            ['videoUrl' => [new AllowedVideoHost]],
        )->passes();
    }

    public function test_it_accepts_allowlisted_https_hosts(): void
    {
        $this->assertTrue($this->passes('https://www.youtube.com/watch?v=abc123'));
        $this->assertTrue($this->passes('https://youtu.be/abc123'));
        $this->assertTrue($this->passes('https://vimeo.com/123456'));
        $this->assertTrue($this->passes('https://player.vimeo.com/video/123456'));
    }

    public function test_it_rejects_plain_http(): void
    {
        $this->assertFalse($this->passes('http://youtube.com/watch?v=abc123'));
    }

    public function test_it_rejects_unknown_hosts(): void
    {
        $this->assertFalse($this->passes('https://evil.example.com/video.mp4'));
    }

    public function test_it_rejects_lookalike_hosts(): void
    {
        $this->assertFalse($this->passes('https://youtube.com.evil.example.com/x'));
        $this->assertFalse($this->passes('https://notyoutube.com/watch?v=abc'));
        $this->assertFalse($this->passes('https://evil.com/?u=https://youtube.com/x'));
    }

    public function test_it_rejects_garbage(): void
    {
        $this->assertFalse($this->passes('not a url'));
        $this->assertFalse($this->passes('javascript:alert(1)'));
    }
}
