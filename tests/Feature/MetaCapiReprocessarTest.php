<?php

namespace Tests\Feature;

use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaCapiReprocessarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_reprocessa_eventos_com_erro_mantendo_event_id(): void
    {
        $config = MetaConversaoConfig::factory()->create(['slug' => 'bancario']);
        $erros = MetaConversaoEvento::factory()->count(3)->comErro()->create([
            'meta_conversao_config_id' => $config->id,
        ]);
        MetaConversaoEvento::factory()->enviado()->create(['meta_conversao_config_id' => $config->id]);

        $idsOriginais = $erros->pluck('event_id')->sort()->values();

        $this->artisan('meta:capi-reprocessar --force')->assertSuccessful();

        Queue::assertPushed(EnviarEventoConversaoMeta::class, 3);

        $this->assertSame(
            $idsOriginais->all(),
            MetaConversaoEvento::query()->where('status', 'pendente')->pluck('event_id')->sort()->values()->all(),
        );
    }

    public function test_filtra_por_campanha(): void
    {
        $a = MetaConversaoConfig::factory()->create(['slug' => 'bancario']);
        $b = MetaConversaoConfig::factory()->create(['slug' => 'concurso']);
        MetaConversaoEvento::factory()->count(2)->comErro()->create(['meta_conversao_config_id' => $a->id]);
        MetaConversaoEvento::factory()->comErro()->create(['meta_conversao_config_id' => $b->id]);

        $this->artisan('meta:capi-reprocessar --campanha=concurso --force')->assertSuccessful();

        Queue::assertPushed(EnviarEventoConversaoMeta::class, 1);
    }

    public function test_pede_confirmacao_sem_force(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        MetaConversaoEvento::factory()->comErro()->create(['meta_conversao_config_id' => $config->id]);

        $this->artisan('meta:capi-reprocessar')
            ->expectsConfirmation('Reprocessar 1 de 1 evento(s)?', 'no')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_respeita_o_limite(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        MetaConversaoEvento::factory()->count(5)->comErro()->create(['meta_conversao_config_id' => $config->id]);

        $this->artisan('meta:capi-reprocessar --limite=2 --force')->assertSuccessful();

        Queue::assertPushed(EnviarEventoConversaoMeta::class, 2);
    }
}
