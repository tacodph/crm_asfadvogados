<?php

namespace Tests\Feature;

use App\Enums\MetaEventName;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use App\Models\MetaConversaoEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrafegoEventoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function pixelFake(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789012345',
                'name' => 'ASF Pixel',
                'last_fired_time' => '2026-09-01T10:00:00+0000',
                'data' => [],
            ], 200),
        ]);
    }

    public function test_eventos_index_paginates_and_filters(): void
    {
        $a = MetaConversaoConfig::factory()->create(['slug' => 'bancario', 'nome_campanha' => 'Bancário']);
        $b = MetaConversaoConfig::factory()->create(['slug' => 'concurso', 'nome_campanha' => 'Concurso']);

        MetaConversaoEvento::factory()->count(3)->enviado()->create([
            'meta_conversao_config_id' => $a->id,
            'event_name' => MetaEventName::Lead,
        ]);
        MetaConversaoEvento::factory()->count(2)->comErro()->create([
            'meta_conversao_config_id' => $a->id,
            'event_name' => MetaEventName::Purchase,
        ]);
        MetaConversaoEvento::factory()->enviado()->create([
            'meta_conversao_config_id' => $b->id,
            'event_name' => MetaEventName::Lead,
        ]);

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/TrafegoEventos')
                ->where('eventos.total', 6)
                ->where('eventos.per_page', 25)
                ->has('eventos.data', 6)
                ->where('contadores.enviado', 4)
                ->where('contadores.erro', 2)
                ->where('detalhe', null));

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.index', ['status' => 'enviado']))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.total', 4));

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.index', ['campanha' => 'concurso']))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.total', 1));

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.index', ['event_name' => 'Purchase']))
            ->assertInertia(fn (Assert $page) => $page->where('eventos.total', 2));
    }

    public function test_listing_never_exposes_payload_or_token(): void
    {
        $config = MetaConversaoConfig::factory()->create(['access_token' => 'EAA'.str_repeat('z', 90)]);
        MetaConversaoEvento::factory()->enviado()->create(['meta_conversao_config_id' => $config->id]);

        $response = $this->actingAs($this->user())
            ->get(route('trafego.eventos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('eventos.data.0.request_payload'));

        $this->assertStringNotContainsString('EAA', $response->getContent());
    }

    public function test_show_includes_the_payload(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        $evento = MetaConversaoEvento::factory()->enviado()->create(['meta_conversao_config_id' => $config->id]);

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.show', $evento))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/TrafegoEventos')
                ->where('detalhe.id', $evento->id)
                ->where('detalhe.request_payload.event_name', $evento->event_name->value));
    }

    public function test_reenviar_requeues_an_errored_event_keeping_the_event_id(): void
    {
        Queue::fake();

        $config = MetaConversaoConfig::factory()->create();
        $evento = MetaConversaoEvento::factory()->comErro()->create(['meta_conversao_config_id' => $config->id]);
        $eventId = $evento->event_id;

        $this->actingAs($this->user())
            ->post(route('trafego.eventos.reenviar', $evento))
            ->assertRedirect();

        Queue::assertPushed(EnviarEventoConversaoMeta::class, fn ($job) => $job->evento->id === $evento->id);

        $evento->refresh();
        $this->assertSame($eventId, $evento->event_id);
        $this->assertSame('pendente', $evento->status->value);
    }

    public function test_reenviar_rejects_a_non_errored_event(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        $evento = MetaConversaoEvento::factory()->enviado()->create(['meta_conversao_config_id' => $config->id]);

        $this->actingAs($this->user())
            ->post(route('trafego.eventos.reenviar', $evento))
            ->assertSessionHasErrors('status');
    }

    public function test_events_from_another_tenant_are_not_found(): void
    {
        $outro = $this->createTenant();
        $alheio = $this->asTenant($outro, fn () => MetaConversaoEvento::factory()->create());

        $this->actingAs($this->user())
            ->get(route('trafego.eventos.show', $alheio))
            ->assertNotFound();

        $this->actingAs($this->user())
            ->post(route('trafego.eventos.reenviar', $alheio))
            ->assertNotFound();
    }

    public function test_diagnostico_defers_the_live_meta_prop(): void
    {
        MetaConversaoConfig::factory()->create(['slug' => 'bancario']);

        $this->actingAs($this->user())
            ->get(route('trafego.diagnostico.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/TrafegoDiagnostico')
                ->has('campanhas', 1)
                ->missing('meta'));

        $this->pixelFake();

        $version = (string) app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($this->user())
            ->get(route('trafego.diagnostico.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'crm/TrafegoDiagnostico',
                'X-Inertia-Partial-Data' => 'meta',
            ])
            ->assertOk()
            ->assertJsonPath('props.meta.bancario.last_fired_time', '2026-09-01T10:00:00+0000');
    }

    public function test_sincronizar_command_snapshots_one_row_per_active_campaign_without_duplicating(): void
    {
        $this->pixelFake();

        MetaConversaoConfig::factory()->create(['slug' => 'bancario']);
        MetaConversaoConfig::factory()->create(['slug' => 'concurso']);
        MetaConversaoConfig::factory()->create(['slug' => 'inativa', 'ativo' => false]);

        $this->artisan('meta:sincronizar-estatisticas')->assertSuccessful();
        $this->assertSame(2, MetaConversaoEstatistica::query()->count());

        $this->artisan('meta:sincronizar-estatisticas')->assertSuccessful();
        $this->assertSame(2, MetaConversaoEstatistica::query()->count());

        $estatistica = MetaConversaoEstatistica::query()->firstOrFail();
        $this->assertNotNull($estatistica->pixel_last_fired_at);
        $this->assertSame(today()->toDateString(), $estatistica->referencia->toDateString());
    }

    public function test_diagnostico_sincronizar_button_creates_snapshots(): void
    {
        $this->pixelFake();
        MetaConversaoConfig::factory()->create(['slug' => 'bancario']);

        $this->actingAs($this->user())
            ->post(route('trafego.diagnostico.sincronizar'))
            ->assertRedirect();

        $this->assertSame(1, MetaConversaoEstatistica::query()->count());
    }
}
