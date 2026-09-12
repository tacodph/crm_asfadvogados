<?php

namespace Tests\Feature;

use App\Models\Funil;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Support\Meta\ResolverCampanhaConversao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResolverCampanhaConversaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function negociacao(string $nomeFunil): Negociacao
    {
        $funil = Funil::factory()->create(['nome' => $nomeFunil]);

        return Negociacao::factory()->make(['funil_id' => $funil->id]);
    }

    public function test_resolves_by_funil_name_over_single_campaign_fallback(): void
    {
        MetaConversaoConfig::factory()->bancario()->create();
        MetaConversaoConfig::factory()->concurso()->create();

        $config = (new ResolverCampanhaConversao)($this->negociacao('Bancário'));

        $this->assertSame('bancario', $config?->slug);
    }

    public function test_returns_null_without_active_campaigns(): void
    {
        MetaConversaoConfig::factory()->inativa()->create();

        $this->assertNull((new ResolverCampanhaConversao)($this->negociacao('Bancário')));
    }

    public function test_falls_back_to_single_active_campaign(): void
    {
        $unica = MetaConversaoConfig::factory()->create(['slug' => 'unica', 'nome_campanha' => 'Única']);

        $config = (new ResolverCampanhaConversao)($this->negociacao('Funil sem correspondência'));

        $this->assertTrue($config?->is($unica));
    }

    public function test_returns_null_on_ambiguity(): void
    {
        MetaConversaoConfig::factory()->bancario()->create();
        MetaConversaoConfig::factory()->concurso()->create();

        $this->assertNull((new ResolverCampanhaConversao)($this->negociacao('Funil genérico')));
    }

    public function test_forced_slug_wins_even_if_inactive(): void
    {
        $inativa = MetaConversaoConfig::factory()->bancario()->inativa()->create();

        $config = (new ResolverCampanhaConversao)($this->negociacao('Qualquer'), 'bancario');

        $this->assertTrue($config?->is($inativa));
    }
}
