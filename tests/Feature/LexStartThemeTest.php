<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LexStartThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_shell_defaults_to_light_appearance(): void
    {
        $this->get($this->centralUrl('home'))
            ->assertOk()
            ->assertSee('#f1f5f9', false)
            ->assertDontSee('class="dark"', false);
    }

    public function test_lexstart_logo_assets_exist(): void
    {
        $this->assertFileExists(public_path('images/logos/logo_circular_whatsApp.jpeg'));
        $this->assertFileExists(public_path('images/logos/logo_circular_fundo_transparente.png'));
    }
}
