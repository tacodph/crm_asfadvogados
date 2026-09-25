<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EmailLeadConta;
use App\Models\EmailLeadProcessado;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessarLeadEmailRecebidoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TipoPessoa::factory()->create(['slug' => 'pf']);
        TipoPessoa::factory()->create(['slug' => 'pj']);
        CanalContato::factory()->create(['slug' => 'email']);
        StatusConsentimento::factory()->create(['slug' => 'opt-in-registrado']);
        StatusComercial::factory()->create(['slug' => 'novo']);
        FinalidadeConsentimento::factory()->create(['slug' => 'contato-comercial']);
        User::factory()->create();
    }

    private function contaComFunil(string $nomeFunil): EmailLeadConta
    {
        $funil = Funil::factory()->create(['nome' => $nomeFunil, 'ordem' => 1]);
        EtapaFunil::factory()->for($funil)->create(['ordem' => 1, 'nome' => 'Novo lead']);

        return EmailLeadConta::factory()->create([
            'funil_id' => $funil->id,
            'finalidade_consentimento_slug' => 'contato-comercial',
        ]);
    }

    private function htmlLead(array $overrides = []): string
    {
        $campos = array_replace([
            'Data do Cadastro' => '21/09/2026 10:00',
            'Nome' => 'Marina Yoshida',
            'Telefone' => '(11) 98888-7777',
            'E-mail' => 'marina@exemplo.com',
            'Valor da Dívida' => 'R$ 12.345,67',
            'PF ou PJ?' => 'PF',
            'Campanha' => 'Bancário 2026',
            'Conjunto' => 'Conjunto A',
            'Anúncio' => 'Anúncio 1',
        ], $overrides);

        $tr = collect($campos)
            ->map(fn (string $v, string $k): string => "<tr><td>{$k}</td><td>{$v}</td></tr>")
            ->implode('');

        return "<table>{$tr}</table>";
    }

    private function processar(string $messageId, EmailLeadConta $conta, string $html): void
    {
        EmailLeadProcessado::factory()->create([
            'message_id' => $messageId,
            'tenant_id' => $this->tenant->id,
            'email_lead_conta_id' => $conta->id,
            'payload_html' => $html,
        ]);

        Bus::dispatchSync(new ProcessarLeadEmailRecebido(
            messageId: $messageId,
            tenantSlug: $this->tenant->slug,
            emailLeadContaId: $conta->id,
            html: $html,
        ));
    }

    public function test_new_lead_creates_contato_and_negociacao_in_the_contas_funil(): void
    {
        $conta = $this->contaComFunil('Funil E-mail');

        $this->processar('<abc@mail.gmail.com>', $conta, $this->htmlLead());

        $contato = Contato::query()->firstOrFail();
        $this->assertSame('marina@exemplo.com', $contato->email);
        $this->assertSame('pf', $contato->tipoPessoa->slug);

        $negociacao = Negociacao::query()->firstOrFail();
        $this->assertSame($conta->funil_id, $negociacao->funil_id);
        $this->assertSame(12345.67, (float) $negociacao->valor);
        $this->assertSame('email', $negociacao->origem_utm['fonte'] ?? null);
        $this->assertSame('Bancário 2026', $negociacao->origem_utm['campanha_nome'] ?? null);

        $registro = EmailLeadProcessado::query()->where('message_id', '<abc@mail.gmail.com>')->firstOrFail();
        $this->assertSame('processado', $registro->status);
        $this->assertSame($contato->id, $registro->contato_id);
        $this->assertSame($negociacao->id, $registro->negociacao_id);
    }

    public function test_two_contas_with_different_funis_open_negociacoes_in_their_own_funil(): void
    {
        $contaA = $this->contaComFunil('Funil A');
        $contaB = $this->contaComFunil('Funil B');

        $this->processar('<a@mail.gmail.com>', $contaA, $this->htmlLead(['E-mail' => 'a@exemplo.com', 'Telefone' => '(11) 90000-0001']));
        $this->processar('<b@mail.gmail.com>', $contaB, $this->htmlLead(['E-mail' => 'b@exemplo.com', 'Telefone' => '(11) 90000-0002']));

        $negA = Negociacao::query()->whereHas('contato', fn ($q) => $q->where('email', 'a@exemplo.com'))->firstOrFail();
        $negB = Negociacao::query()->whereHas('contato', fn ($q) => $q->where('email', 'b@exemplo.com'))->firstOrFail();

        $this->assertSame($contaA->funil_id, $negA->funil_id);
        $this->assertSame($contaB->funil_id, $negB->funil_id);
        $this->assertNotSame($negA->funil_id, $negB->funil_id);
    }

    public function test_existing_contato_is_reused_but_a_new_negociacao_is_created(): void
    {
        $conta = $this->contaComFunil('Funil E-mail');
        $contato = Contato::factory()->create(['email' => 'marina@exemplo.com']);

        $this->processar('<abc@mail.gmail.com>', $conta, $this->htmlLead());

        $this->assertSame(1, Contato::query()->count());
        $this->assertSame($contato->id, Negociacao::query()->firstOrFail()->contato_id);
        $this->assertSame(1, Negociacao::query()->count());
    }

    public function test_reprocessing_the_same_message_id_is_a_no_op(): void
    {
        $conta = $this->contaComFunil('Funil E-mail');

        $this->processar('<abc@mail.gmail.com>', $conta, $this->htmlLead());
        $this->assertSame(1, Negociacao::query()->count());

        // Segunda execução do mesmo Job para o mesmo registro, já "processado".
        Bus::dispatchSync(new ProcessarLeadEmailRecebido(
            messageId: '<abc@mail.gmail.com>',
            tenantSlug: $this->tenant->slug,
            emailLeadContaId: $conta->id,
            html: $this->htmlLead(),
        ));

        $this->assertSame(1, Negociacao::query()->count());
        $this->assertSame(1, Contato::query()->count());
    }

    public function test_unknown_tenant_marks_registro_as_erro_without_throwing(): void
    {
        $conta = $this->contaComFunil('Funil E-mail');

        EmailLeadProcessado::factory()->create([
            'message_id' => '<x@mail.gmail.com>',
            'tenant_id' => $this->tenant->id,
            'email_lead_conta_id' => $conta->id,
            'payload_html' => $this->htmlLead(),
        ]);

        Bus::dispatchSync(new ProcessarLeadEmailRecebido(
            messageId: '<x@mail.gmail.com>',
            tenantSlug: 'tenant-que-nao-existe',
            emailLeadContaId: $conta->id,
            html: $this->htmlLead(),
        ));

        $registro = EmailLeadProcessado::query()->where('message_id', '<x@mail.gmail.com>')->firstOrFail();
        $this->assertSame('erro', $registro->status);
        $this->assertStringContainsString('tenant-que-nao-existe', (string) $registro->erro);
    }

    public function test_corrupted_html_marks_registro_as_erro_without_throwing(): void
    {
        $conta = $this->contaComFunil('Funil E-mail');

        $this->processar('<corrompido@mail.gmail.com>', $conta, '<p>sem tabela nenhuma</p>');

        $registro = EmailLeadProcessado::query()->where('message_id', '<corrompido@mail.gmail.com>')->firstOrFail();
        $this->assertSame('erro', $registro->status);
        $this->assertStringContainsString('Nenhuma tabela', (string) $registro->erro);
        $this->assertSame(0, Negociacao::query()->count());
    }
}
