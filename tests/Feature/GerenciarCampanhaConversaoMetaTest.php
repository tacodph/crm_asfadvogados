<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GerenciarCampanhaConversaoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_campanha_nova(): void
    {
        $this->artisan('meta:campanha', ['--tenant' => $this->tenant->slug, '--slug' => 'bancario'])
            ->expectsQuestion('Nome da campanha', 'Bancário')
            ->expectsQuestion('Pixel / Dataset ID', '2050053805929053')
            ->expectsQuestion('Access token', 'EAAtoken-bancario-0001')
            ->expectsQuestion('Slug da finalidade de consentimento', 'marketing')
            ->assertSuccessful();

        $config = MetaConversaoConfig::query()->where('slug', 'bancario')->firstOrFail();

        $this->assertSame('Bancário', $config->nome_campanha);
        $this->assertSame('2050053805929053', $config->pixel_id);
        $this->assertSame('EAAtoken-bancario-0001', $config->access_token);
        $this->assertSame('0001', $config->token_ultimos4);
        $this->assertSame($this->tenant->id, $config->tenant_id);
    }

    public function test_e_idempotente(): void
    {
        $inputs = fn ($cmd) => $cmd
            ->expectsQuestion('Nome da campanha', 'Bancário')
            ->expectsQuestion('Pixel / Dataset ID', '2050053805929053')
            ->expectsQuestion('Access token', 'EAAtoken-bancario-0001')
            ->expectsQuestion('Slug da finalidade de consentimento', 'marketing')
            ->assertSuccessful();

        $inputs($this->artisan('meta:campanha', ['--tenant' => $this->tenant->slug, '--slug' => 'bancario']));

        $this->artisan('meta:campanha', ['--tenant' => $this->tenant->slug, '--slug' => 'bancario'])
            ->expectsQuestion('Nome da campanha', 'Bancário')
            ->expectsQuestion('Pixel / Dataset ID', '2050053805929053')
            ->expectsQuestion('Access token (Enter em branco mantém o atual)', '')
            ->expectsQuestion('Slug da finalidade de consentimento', 'marketing')
            ->assertSuccessful();

        $this->assertSame(1, MetaConversaoConfig::query()->where('slug', 'bancario')->count());
        $this->assertSame(
            'EAAtoken-bancario-0001',
            MetaConversaoConfig::query()->where('slug', 'bancario')->firstOrFail()->access_token,
        );
    }

    public function test_flag_testar_valida_token_na_graph_api(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '2050053805929053',
                'name' => 'Pixel ASF Bancário',
                'last_fired_time' => '2026-09-01T10:00:00+0000',
            ]),
        ]);

        $this->artisan('meta:campanha', [
            '--tenant' => $this->tenant->slug,
            '--slug' => 'bancario',
            '--testar' => true,
        ])
            ->expectsQuestion('Nome da campanha', 'Bancário')
            ->expectsQuestion('Pixel / Dataset ID', '2050053805929053')
            ->expectsQuestion('Access token', 'EAAtoken-bancario-0001')
            ->expectsQuestion('Slug da finalidade de consentimento', 'marketing')
            ->assertSuccessful();

        $config = MetaConversaoConfig::query()->where('slug', 'bancario')->firstOrFail();

        $this->assertTrue($config->token_valido);
        $this->assertNotNull($config->token_verificado_em);
    }

    public function test_falha_sem_tenant_valido(): void
    {
        $this->artisan('meta:campanha', ['--tenant' => 'nao-existe', '--slug' => 'bancario'])
            ->assertFailed();
    }
}
