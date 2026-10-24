<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_assets_exist(): void
    {
        foreach ([
            'favicon.ico',
            'favicon-32x32.png',
            'favicon-16x16.png',
            'apple-touch-icon.png',
            'site.webmanifest',
            'images/kirwa-net-mark.png',
            'images/kirwa-net-logo.png',
        ] as $asset) {
            $this->assertFileExists(public_path($asset), "Missing public/{$asset}");
        }
    }

    public function test_portal_uses_the_local_logo_and_favicon(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/images/kirwa-net-mark.png', false)
            ->assertSee('/favicon.ico', false)
            ->assertSee('/site.webmanifest', false)
            ->assertSee(config('app.name'));
    }

    public function test_login_uses_the_brand_lockup_and_favicon(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('/images/kirwa-net-logo.png', false)
            ->assertSee('/favicon.ico', false)
            ->assertSee('Admin Login');
    }
}
