<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeOnePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_onepage_landing_sections(): void
    {
        $this->get($this->centralUrl('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_welcome_page_includes_tailwick_section_ids_in_ssr_payload(): void
    {
        $response = $this->get($this->centralUrl('home'));

        $response->assertOk();

        $content = $response->getContent();

        foreach (['home', 'features', 'about', 'pricing', 'contact'] as $sectionId) {
            $this->assertStringContainsString(
                "id=\"{$sectionId}\"",
                $content,
                "Missing one-page section #{$sectionId}",
            );
        }
    }
}
