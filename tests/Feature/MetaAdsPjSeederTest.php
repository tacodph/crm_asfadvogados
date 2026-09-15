<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\MetaAdsPjSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaAdsPjSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pj_meta_ads_funnel_and_imports_empresas_contatos_negociacoes(): void
    {
        $seeder = new MetaAdsPjSeeder;
        $seeder->dataPath = base_path('tests/fixtures/meta-ads-pj-mini.json');
        $seeder->run();

        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();

        app(CurrentTenant::class)->runAs($tenant, function (): void {
            $funil = Funil::query()->where('slug', 'pj-meta-ads')->firstOrFail();

            $this->assertSame('Parte empresarial PJ Meta Ads', $funil->nome);
            $this->assertSame(11, EtapaFunil::query()->where('funil_id', $funil->id)->count());
            $this->assertTrue(
                EtapaFunil::query()
                    ->where('funil_id', $funil->id)
                    ->where('nome', 'Follow-up 1')
                    ->exists(),
            );
            $this->assertTrue(
                EtapaFunil::query()
                    ->where('funil_id', $funil->id)
                    ->where('nome', 'No show')
                    ->where('resultado', 'perdido')
                    ->exists(),
            );
            $this->assertTrue(
                EtapaFunil::query()
                    ->where('funil_id', $funil->id)
                    ->where('nome', 'Contrato fechado')
                    ->where('resultado', 'ganho')
                    ->exists(),
            );

            $this->assertSame(3, Empresa::query()->count());
            $this->assertSame(3, Contato::query()->count());
            $this->assertSame(3, Negociacao::query()->where('funil_id', $funil->id)->count());
            $this->assertGreaterThanOrEqual(6, HistoricoNegociacao::query()->count());
            $this->assertSame(3, Empresa::query()->whereNull('cnpj')->count());
            $this->assertSame(
                3,
                Empresa::query()
                    ->where('conflito_texto', 'Lead Meta Ads REV-PJ — CNPJ não informado no formulário.')
                    ->count(),
            );

            $this->assertSame(
                3,
                Negociacao::query()->where('funil_id', $funil->id)->whereNotNull('empresa_id')->count(),
            );

            $this->assertTrue(Contato::query()->where('telefone', '91992001123')->exists());
            $this->assertTrue(Contato::query()->where('telefone', '61992017262')->exists());
            $this->assertTrue(Empresa::query()->where('nome', 'like', 'Empresa — Jacqueline%')->exists());

            $this->assertSame(
                3,
                Contato::query()
                    ->whereHas('canalContato', fn ($q) => $q->where('slug', 'meta-ads'))
                    ->count(),
            );
            $this->assertSame(
                3,
                Negociacao::query()
                    ->where('funil_id', $funil->id)
                    ->whereHas('canalContato', fn ($q) => $q->where('slug', 'meta-ads'))
                    ->count(),
            );
            $this->assertSame(
                0,
                Negociacao::query()
                    ->where('funil_id', $funil->id)
                    ->whereHas('canalContato', fn ($q) => $q->where('slug', 'whatsapp'))
                    ->count(),
            );

            $this->assertTrue(
                Negociacao::query()
                    ->where('funil_id', $funil->id)
                    ->whereHas('etapaFunil', fn ($q) => $q->where('nome', 'Novo lead'))
                    ->exists(),
            );

            $this->assertTrue(
                Negociacao::query()
                    ->where('funil_id', $funil->id)
                    ->whereHas('etapaFunil', fn ($q) => $q->where('nome', 'Reunião agendada'))
                    ->exists(),
            );
        });
    }
}
