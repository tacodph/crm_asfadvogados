<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\QualificacaoAtendimentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualificacaoAtendimentosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_contacts_negotiations_history_and_proposals(): void
    {
        $seeder = new QualificacaoAtendimentosSeeder;
        $seeder->dataPath = base_path('tests/fixtures/qualificacao-atendimentos-mini.json');
        $seeder->run();

        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();

        app(CurrentTenant::class)->runAs($tenant, function (): void {
            $this->assertSame(2, Contato::query()->count());
            $this->assertSame(2, Negociacao::query()->count());
            $this->assertSame(1, Proposta::query()->count());
            $this->assertGreaterThan(2, HistoricoNegociacao::query()->count());
            $this->assertTrue(User::query()->where('email', 'bruno.gabriel@asfadvogados.adv.br')->exists());
            $this->assertTrue(User::query()->where('email', 'flavio.augusto@asfadvogados.adv.br')->exists());
            $this->assertTrue(Contato::query()->where('telefone', '61999990001')->exists());
            $this->assertTrue(Negociacao::query()->where('assunto', 'PMDF - QUESTÕES')->exists());
            $this->assertTrue(Proposta::query()->where('codigo', 'PR-AGO-001')->exists());
            $this->assertTrue(
                Negociacao::query()
                    ->whereHas('statusAtendimento', fn ($q) => $q->where('slug', 'contrato-fechado'))
                    ->exists(),
            );
            $this->assertTrue(
                Negociacao::query()
                    ->whereHas('statusQualificacao', fn ($q) => $q->where('slug', 'desqualificado'))
                    ->exists(),
            );
        });
    }
}
