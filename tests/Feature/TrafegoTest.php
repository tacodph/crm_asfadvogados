<?php

namespace Tests\Feature;

use App\Models\FinalidadeConsentimento;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrafegoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Evita SSR tentar falar com o Vite dev quando `public/hot` existe localmente.
        if (is_file(public_path('hot'))) {
            @unlink(public_path('hot'));
        }

        Http::preventStrayRequests();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadValido(array $overrides = []): array
    {
        FinalidadeConsentimento::query()->firstOrCreate(
            ['slug' => 'marketing'],
            ['nome' => 'Marketing', 'ordem' => 1],
        );

        return [
            'nome_campanha' => 'Bancário Meta',
            'slug' => 'bancario-meta',
            'pixel_id' => '123456789012345',
            'access_token' => 'EAA'.str_repeat('a', 60),
            'api_version' => 'v21.0',
            'action_source' => 'system_generated',
            'origem_url' => 'https://www.instagram.com/asfadvogados_/',
            'finalidade_consentimento_slug' => 'marketing',
            'test_event_code' => 'TEST123',
            'ativo' => true,
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flash(): array
    {
        $flash = session('inertia.flash_data');

        $this->assertIsArray($flash);

        return $flash;
    }

    public function test_index_renders_without_leaking_the_token(): void
    {
        MetaConversaoConfig::factory()->create(['access_token' => 'EAA'.str_repeat('z', 90)]);

        $response = $this->actingAs($this->user())
            ->withoutVite()
            ->get(route('trafego.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Trafego')
                ->has('campanhas', 1)
                ->has('kpis', 4)
                ->has('finalidades')
                ->missing('campanhas.0.access_token'));

        $this->assertStringNotContainsString('EAA', $response->getContent());
    }

    public function test_store_requires_an_access_token(): void
    {
        $this->actingAs($this->user())
            ->post(route('trafego.store'), $this->payloadValido(['access_token' => '']))
            ->assertSessionHasErrors('access_token');

        $this->assertSame(0, MetaConversaoConfig::query()->count());
    }

    public function test_store_creates_an_encrypted_campaign(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => '(#100) Missing Permission',
                        'code' => 100,
                    ],
                ], 400)
                ->push(['events_received' => 1], 200),
        ]);

        $user = $this->user();

        $this->actingAs($user)
            ->post(route('trafego.store'), $this->payloadValido())
            ->assertRedirect(route('trafego.index'));

        $config = MetaConversaoConfig::query()->firstOrFail();

        $this->assertSame('bancario-meta', $config->slug);
        $this->assertSame($user->id, $config->atualizado_por_user_id);
        $this->assertSame('aaaa', $config->token_ultimos4);
        $this->assertTrue($config->token_valido);
        $this->assertNotNull($config->token_verificado_em);

        $cru = DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token');
        $this->assertStringNotContainsString('EAA', (string) $cru);
        $this->assertSame($this->payloadValido()['access_token'], $config->access_token);
        $this->assertStringContainsString('Token validado', $this->flash()['toast']['message']);
    }

    public function test_store_rejects_a_duplicate_slug_in_the_same_tenant(): void
    {
        MetaConversaoConfig::factory()->create(['slug' => 'bancario-meta']);

        $this->actingAs($this->user())
            ->post(route('trafego.store'), $this->payloadValido())
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, MetaConversaoConfig::query()->count());
    }

    public function test_update_without_a_token_keeps_the_stored_one(): void
    {
        $config = MetaConversaoConfig::factory()->create(['access_token' => 'EAA'.str_repeat('k', 60)]);
        $cruAntes = DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token');

        $this->actingAs($this->user())
            ->patch(route('trafego.update', $config), $this->payloadValido([
                'slug' => $config->slug,
                'nome_campanha' => 'Nome novo',
                'access_token' => '',
            ]))
            ->assertRedirect(route('trafego.index'));

        $config->refresh();
        $this->assertSame('Nome novo', $config->nome_campanha);
        $this->assertSame(
            $cruAntes,
            DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token'),
        );
    }

    public function test_update_with_a_new_token_rotates_and_revalidates(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => '(#100) Missing Permission',
                        'code' => 100,
                    ],
                ], 400)
                ->push(['events_received' => 1], 200),
        ]);

        $config = MetaConversaoConfig::factory()->tokenVerificado()->create();
        $cruAntes = DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token');

        $this->actingAs($this->user())
            ->patch(route('trafego.update', $config), $this->payloadValido([
                'slug' => $config->slug,
                'access_token' => 'EAA'.str_repeat('n', 70),
            ]))
            ->assertRedirect(route('trafego.index'));

        $config->refresh();
        $this->assertNotSame(
            $cruAntes,
            DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token'),
        );
        $this->assertSame('EAA'.str_repeat('n', 70), $config->access_token);
        $this->assertTrue($config->token_valido);
        $this->assertNotNull($config->token_verificado_em);
    }

    public function test_cannot_touch_a_campaign_from_another_tenant(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => MetaConversaoConfig::factory()->create());

        $this->actingAs($this->user())
            ->patch(route('trafego.update', $alheia), $this->payloadValido())
            ->assertNotFound();

        $this->actingAs($this->user())
            ->delete(route('trafego.destroy', $alheia))
            ->assertNotFound();
    }

    public function test_destroy_keeps_a_campaign_that_has_events(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        MetaConversaoEvento::factory()->create(['meta_conversao_config_id' => $config->id]);

        $this->actingAs($this->user())
            ->delete(route('trafego.destroy', $config))
            ->assertRedirect(route('trafego.index'));

        $config->refresh();
        $this->assertFalse($config->ativo);
        $this->assertDatabaseHas('meta_conversao_configs', ['id' => $config->id]);
    }

    public function test_destroy_deletes_a_campaign_without_events(): void
    {
        $config = MetaConversaoConfig::factory()->create();

        $this->actingAs($this->user())
            ->delete(route('trafego.destroy', $config))
            ->assertRedirect(route('trafego.index'));

        $this->assertDatabaseMissing('meta_conversao_configs', ['id' => $config->id]);
    }

    public function test_testar_conexao_marks_the_stored_token_as_valid_via_events_fallback(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => '(#100) Missing Permission',
                        'code' => 100,
                    ],
                ], 400)
                ->push(['events_received' => 1], 200),
        ]);

        $config = MetaConversaoConfig::factory()->create();

        $this->actingAs($this->user())
            ->post(route('trafego.testar-conexao', $config))
            ->assertRedirect();

        $config->refresh();
        $this->assertTrue($config->token_valido);
        $this->assertNotNull($config->token_verificado_em);
        $this->assertStringContainsString('envio de eventos', $this->flash()['toast']['message']);
    }

    public function test_testar_conexao_marks_the_stored_token_as_valid(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789012345',
                'name' => 'ASF Pixel',
                'last_fired_time' => '2026-09-01T10:00:00+0000',
            ], 200),
        ]);

        $config = MetaConversaoConfig::factory()->create();

        $this->actingAs($this->user())
            ->post(route('trafego.testar-conexao', $config))
            ->assertRedirect();

        $config->refresh();
        $this->assertTrue($config->token_valido);
        $this->assertNotNull($config->token_verificado_em);
        $this->assertStringContainsString('ASF Pixel', $this->flash()['toast']['message']);
    }

    public function test_testar_conexao_with_an_unsaved_token_does_not_persist_verification(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '1', 'name' => 'ASF Pixel'], 200),
        ]);

        $config = MetaConversaoConfig::factory()->create();

        $this->actingAs($this->user())
            ->post(route('trafego.testar-conexao', $config), ['access_token' => 'EAA'.str_repeat('t', 40)])
            ->assertRedirect();

        $this->assertNull($config->fresh()->token_verificado_em);
    }

    public function test_evento_teste_requires_a_test_event_code(): void
    {
        $config = MetaConversaoConfig::factory()->create(['test_event_code' => null]);

        $this->actingAs($this->user())
            ->post(route('trafego.evento-teste', $config))
            ->assertSessionHasErrors('test_event_code');

        $this->assertSame(0, MetaConversaoEvento::query()->count());
    }

    public function test_evento_teste_sends_a_synthetic_lead(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'events_received' => 1,
                'fbtrace_id' => 'AbC123',
            ], 200),
        ]);

        $config = MetaConversaoConfig::factory()->create(['test_event_code' => 'TEST999']);

        $this->actingAs($this->user())
            ->post(route('trafego.evento-teste', $config))
            ->assertRedirect();

        $evento = MetaConversaoEvento::query()->firstOrFail();

        $this->assertTrue($evento->is_teste);
        $this->assertStringStartsWith('teste_', $evento->event_id);
        $this->assertSame(1, $evento->events_received);
        $this->assertStringContainsString('1', $this->flash()['toast']['message']);
    }
}
