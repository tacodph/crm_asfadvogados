<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailLeadConta;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;

class TrafegoEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        FinalidadeConsentimento::factory()->create(['slug' => 'contato-comercial']);
        Funil::factory()->create(['nome' => 'Comercial', 'ordem' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'nome' => 'Leads Meta Ads',
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'leads@asfadvogados.com.br',
            'password' => 'senha-de-app-secreta',
            'pasta' => 'INBOX',
            'funil_id' => Funil::query()->firstOrFail()->id,
            'finalidade_consentimento_slug' => 'contato-comercial',
            'ativo' => true,
            ...$overrides,
        ];
    }

    public function test_index_renders_without_leaking_password(): void
    {
        EmailLeadConta::factory()->create(['funil_id' => Funil::query()->firstOrFail()->id]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('trafego.email.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/TrafegoEmail')
                ->has('contas', 1)
                ->has('funis', 1)
                ->missing('contas.0.password'));

        $this->assertStringNotContainsString('senha-de-app', $response->getContent());
    }

    public function test_store_creates_conta_with_encrypted_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('trafego.email.store'), $this->payload())
            ->assertRedirect(route('trafego.email.index'));

        $conta = EmailLeadConta::query()->firstOrFail();
        $this->assertSame('leads@asfadvogados.com.br', $conta->username);
        $this->assertSame('senha-de-app-secreta', $conta->password);
        $this->assertDatabaseHas('email_leads_contas', ['username' => 'leads@asfadvogados.com.br']);

        $raw = DB::table('email_leads_contas')->value('password');
        $this->assertStringNotContainsString('senha-de-app-secreta', (string) $raw);
    }

    public function test_update_with_blank_password_keeps_the_previous_one(): void
    {
        $conta = EmailLeadConta::factory()->create([
            'funil_id' => Funil::query()->firstOrFail()->id,
            'password' => 'senha-original',
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('trafego.email.update', $conta), $this->payload(['password' => '', 'nome' => 'Renomeada']))
            ->assertRedirect(route('trafego.email.index'));

        $conta->refresh();
        $this->assertSame('Renomeada', $conta->nome);
        $this->assertSame('senha-original', $conta->password);
    }

    public function test_destroy_removes_conta(): void
    {
        $conta = EmailLeadConta::factory()->create(['funil_id' => Funil::query()->firstOrFail()->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('trafego.email.destroy', $conta))
            ->assertRedirect(route('trafego.email.index'));

        $this->assertDatabaseMissing('email_leads_contas', ['id' => $conta->id]);
    }

    public function test_testar_conexao_records_failure_status(): void
    {
        $conta = EmailLeadConta::factory()->create(['funil_id' => Funil::query()->firstOrFail()->id]);

        $this->mock(ClientManager::class, function ($mock): void {
            $mock->shouldReceive('make')->andThrow(new ConnectionFailedException('Falha simulada de conexão.'));
        });

        $this->actingAs(User::factory()->create())
            ->post(route('trafego.email.testar-conexao', $conta))
            ->assertRedirect(route('trafego.email.index'));

        $conta->refresh();
        $this->assertSame('erro', $conta->ultimo_status);
        $this->assertSame('Falha simulada de conexão.', $conta->ultimo_erro);
    }

    public function test_testar_conexao_maps_google_auth_failure_to_friendly_message(): void
    {
        $conta = EmailLeadConta::factory()->create(['funil_id' => Funil::query()->firstOrFail()->id]);

        $this->mock(ClientManager::class, function ($mock): void {
            $mock->shouldReceive('make')->andThrow(
                new ImapServerErrorException('NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)'),
            );
        });

        $this->actingAs(User::factory()->create())
            ->post(route('trafego.email.testar-conexao', $conta))
            ->assertRedirect(route('trafego.email.index'));

        $conta->refresh();
        $this->assertSame('erro', $conta->ultimo_status);
        $this->assertStringContainsString('senha de app', $conta->ultimo_erro);
    }

    public function test_store_strips_spaces_from_google_app_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('trafego.email.store'), $this->payload([
                'password' => 'abcd efgh ijkl mnop',
            ]))
            ->assertRedirect(route('trafego.email.index'));

        $conta = EmailLeadConta::query()->first();
        $this->assertNotNull($conta);
        $this->assertSame('abcdefghijklmnop', $conta->password);
    }
}
