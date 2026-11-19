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
            'android-chrome-192x192.png',
            'android-chrome-512x512.png',
            'site.webmanifest',
            'fonts/inter.woff2',
            'fonts/plus-jakarta-sans.woff2',
            'images/teto-net-mark.png',
            'images/teto-net-logo.png',
            'images/teto-net-mark-160.jpg',
        ] as $asset) {
            $this->assertFileExists(public_path($asset), "Missing public/{$asset}");
        }
    }

    public function test_portal_uses_the_local_logo_and_favicon(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/images/teto-net-mark.png', false)
            ->assertSee('/favicon.ico', false)
            ->assertSee('/site.webmanifest', false)
            ->assertSee(config('app.name'));
    }

    public function test_login_uses_the_brand_lockup_and_favicon(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('/images/teto-net-logo.png', false)
            ->assertSee('/favicon.ico', false)
            ->assertSee('Admin Login');
    }

    public function test_pwa_manifest_uses_the_teto_net_brand_and_theme(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);

        $this->assertSame('Teto_Net', $manifest['name']);
        $this->assertSame('Teto_Net', $manifest['short_name']);
        $this->assertSame('#0F766E', $manifest['theme_color']);
    }
}
