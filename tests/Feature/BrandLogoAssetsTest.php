<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandLogoAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lexstart_logo_assets_exist_in_public_images(): void
    {
        $assets = [
            'images/logos/logo_circular_fundo_transparente.png',
            'images/logos/logo_circulas_fundo_branco.jpeg',
            'images/logos/logo_circular_whatsApp.jpeg',
            'images/logos/logo_e_lexstarts.jpeg',
            'images/logos/logo_e_lexstarts_transparente.png',
            'images/logos/logo_circular_retangular.jpeg',
            'images/logos/logo_circular.jpeg',
        ];

        foreach ($assets as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_welcome_page_exposes_lexstart_favicon(): void
    {
        $this->get($this->centralUrl('home'))
            ->assertOk()
            ->assertSee('/images/logos/logo_circular_fundo_transparente.png', false);
    }
}
