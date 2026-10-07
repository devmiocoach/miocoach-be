<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The root layout must expose the CSRF token as a meta tag.
 *
 * Raw `fetch` uploads (coach onboarding certifications, insurance, gallery
 * photos and intro video) read it to set `X-CSRF-TOKEN`. Without the tag those
 * requests get a 419 redirect that `fetch` silently follows, so the upload
 * fails without any error reaching the user.
 */
class CsrfTokenMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_layout_exposes_the_csrf_token_meta_tag(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('content="'.csrf_token().'"', false);
    }
}
