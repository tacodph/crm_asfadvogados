# Prompt 01 — Estratégia de captura (IMAP), tela de configuração e agendamento

## Objetivo

Montar a infraestrutura de entrada: uma tela em `/trafego` para a equipe de tráfego
cadastrar a caixa de e-mail (credenciais IMAP) e escolher **para qual funil** e **sob qual
finalidade de consentimento (LGPD)** os leads recebidos entram, mais o comando agendado que
lê a caixa a cada poucos minutos e despacha cada mensagem nova de forma idempotente. O Job
de processamento em si é o prompt 03.

A finalidade de consentimento é um segundo campo de configuração, no mesmo espírito do
funil: sem ele a Action do prompt 03 não tem base legal pra registrar o consentimento do
`Contato` (mesmo desenho já usado por `MetaConversaoConfig::finalidade_consentimento_slug`
— ver `TrafegoController`). Diferente do funil, aqui é **obrigatório** (não nullable): sem
finalidade escolhida não dá pra abrir a negociação.

## Estratégia de captura: IMAP vs API do Gmail

Duas abordagens possíveis:

| | IMAP (`webklex/laravel-imap`) | API do Gmail (`google/apiclient`) |
|---|---|---|
| Autenticação | Senha de app (Workspace) ou XOAUTH2 | OAuth2 completo (client id/secret, refresh token) |
| "Tempo real" | Polling a cada N minutos | Polling ou push via Cloud Pub/Sub (`users.watch`) |
| Infra extra | Nenhuma | Projeto no Google Cloud, tópico Pub/Sub, endpoint de webhook público, renovação do `watch` a cada 7 dias |
| Complexidade de código | Baixa — 1 pacote, 1 comando agendado | Alta — fluxo OAuth, refresh token, assinatura do webhook |
| Adequado para | Uma ou poucas caixas dedicadas, sem necessidade de latência sub-minuto | Múltiplas contas de usuários finais, necessidade real de push |

**Decisão: IMAP com `webklex/laravel-imap`, agendamento a cada 5 minutos.** O caso de uso é
uma caixa dedicada (não a caixa de um usuário final), não há requisito de latência
sub-minuto, e o pacote já entrega parsing de mensagens/anexos pronto para Laravel. A API do
Gmail só se justificaria se precisássemos de push verdadeiro (segundos) ou de ler caixas de
usuários finais que não podem compartilhar senha de app — não é o caso aqui.

Dentro de IMAP, duas formas de autenticar:
- **Senha de app do Google Workspace** (Conta Google → Segurança → Senhas de app) — mais
  simples, recomendada aqui. Requer que o admin do Workspace permita senha de app para a
  conta usada (a maioria dos Workspaces já permite por padrão, mesmo com 2FA).
- **OAuth2 (XOAUTH2)** — necessário só se o admin do Workspace bloquear senhas de app por
  política. `webklex/laravel-imap` suporta XOAUTH2, mas exige o mesmo fluxo de client
  id/secret/refresh token da API do Gmail — se for parar aqui, considerar migrar para a
  API do Gmail direto, já que o custo de OAuth já foi pago. Fallback documentado, não
  implementado agora.

## Configuração: DB-first via tela `/trafego`, não `.env`

Mesma decisão arquitetural já tomada para a API de Conversões da Meta
(`documents/api_meta/arquitetura_capi.md`, §7): credenciais e políticas ficam **numa
tabela**, cadastradas pela equipe de tráfego numa tela, não em variável de ambiente. Isso
resolve de quebra a pergunta "para qual funil vai o lead" — vira um campo do formulário, não
uma decisão hardcoded no código — e já nasce multi-tenant (cada linha pertence a um tenant
via `BelongsToTenant`, sem precisar de array de contas em config nem de índice `0` como
fallback).

O segredo (senha IMAP) é cifrado em repouso reusando o cast já existente
`App\Casts\SegredoMeta` (`META_CAPI_ENCRYPTION_KEY`, chave dedicada independente do
`APP_KEY`) — não criar uma segunda chave/cast só para isto, o mecanismo já é genérico.

## Passo 1 — Dependências

```bash
composer require webklex/laravel-imap
composer require symfony/dom-crawler symfony/css-selector
```

`symfony/dom-crawler`/`css-selector` só existem hoje via dependência transitiva de outro
pacote (não confiável para produção) — precisam entrar como dependência direta porque o
prompt 02 depende deles.

Publicar a config do pacote de IMAP:

```bash
php artisan vendor:publish --provider="Webklex\IMAP\Providers\LaravelServiceProvider"
```

## Passo 2 — Migrations

### `email_leads_contas` (config da caixa de e-mail — cadastrada pela UI)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_leads_contas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome'); // rótulo livre, ex. "Leads Meta Ads - ASF"
            $table->string('host')->default('imap.gmail.com');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption')->default('ssl');
            $table->string('username');
            // Cifrada em repouso pelo cast App\Casts\SegredoMeta (mesma chave da CAPI).
            $table->text('password');
            $table->string('pasta')->default('INBOX');
            $table->foreignId('funil_id')->constrained('funis')->restrictOnDelete();
            $table->string('finalidade_consentimento_slug');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultima_captura_em')->nullable();
            $table->string('ultimo_status')->nullable(); // ok|erro
            $table->text('ultimo_erro')->nullable();
            $table->foreignId('atualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'username']);
            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_leads_contas');
    }
};
```

`funil_id` é `restrictOnDelete()` de propósito: não deixar apagar um funil que uma conta de
e-mail depende dele sem que alguém troque a conta de funil primeiro (mesmo cuidado que
dados de negociação já têm no resto do CRM).

### `email_leads_processados` (log append-only de idempotência)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_leads_processados', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_lead_conta_id')->nullable()->constrained('email_leads_contas')->nullOnDelete();
            $table->foreignId('contato_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('negociacao_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pendente'); // pendente|processado|erro
            $table->text('erro')->nullable();
            $table->text('payload_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_leads_processados');
    }
};
```

`message_id` é o `Message-ID` do cabeçalho RFC 5322 — único no mundo, é a chave de
idempotência real (não depender da flag `\Seen` do IMAP: se o Job falhar depois de marcar
como lida, o lead se perde; se falhar antes, a mensagem é reprocessada no próximo poll e a
constraint `unique` barra a duplicata). `email_lead_conta_id` guarda **de qual conta** o
e-mail veio — necessário para o comando de reprocessamento (prompt 04) saber o funil/tenant
certos mesmo que a conta tenha sido editada depois.

`payload_html` guarda o corpo bruto recebido — auditoria e permite reprocessar
(`04_hardening_observabilidade.md`) sem precisar ir buscar a mensagem na caixa de novo.

## Passo 3 — Models

`App\Models\EmailLeadConta` — reusa `App\Casts\SegredoMeta` exatamente como
`MetaConversaoConfig` reusa para `access_token`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SegredoMeta;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('email_leads_contas')]
#[Fillable([
    'nome', 'host', 'port', 'encryption', 'username', 'password', 'pasta',
    'funil_id', 'finalidade_consentimento_slug', 'ativo', 'ultima_captura_em',
    'ultimo_status', 'ultimo_erro', 'atualizado_por_user_id',
])]
#[Hidden(['password'])]
class EmailLeadConta extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'password' => SegredoMeta::class,
            'ultima_captura_em' => 'datetime',
        ];
    }

    /** @return array{host: string, port: int, encryption: string, username: string, password: string, protocol: string} */
    public function credenciaisImap(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'validate_cert' => true,
            'username' => $this->username,
            'password' => (string) $this->password,
            'protocol' => 'imap',
        ];
    }

    /** @param Builder<EmailLeadConta> $query */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /** @return BelongsTo<Funil, $this> */
    public function funil(): BelongsTo
    {
        return $this->belongsTo(Funil::class);
    }
}
```

`App\Models\EmailLeadProcessado` (fillable: `message_id`, `tenant_id`,
`email_lead_conta_id`, `contato_id`, `negociacao_id`, `status`, `erro`, `payload_html`) —
sem `BelongsToTenant`: é escrito a partir do comando de captura, antes/fora de qualquer
contexto de tenant resolvido, então guarda `tenant_id` explicitamente em vez de depender do
escopo automático.

## Passo 4 — Tela `/trafego → E-mail`

Mesmo padrão de CRUD das outras abas de `/trafego` (`TrafegoController`/
`TrafegoInvestimentoController`): controller dedicado, FormRequests com "deixe em branco
para manter a senha" no update, senha nunca volta pro front, teste de conexão antes de
salvar/depois de editar.

`app/Http/Controllers/TrafegoEmailController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailLeadContaRequest;
use App\Http\Requests\UpdateEmailLeadContaRequest;
use App\Models\EmailLeadConta;
use App\Models\Funil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class TrafegoEmailController extends Controller
{
    public function index(): Response
    {
        $contas = EmailLeadConta::query()
            ->with(['funil:id,nome', 'atualizadoPor:id,name'])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/TrafegoEmail', [
            'contas' => $contas->map(fn (EmailLeadConta $conta): array => [
                'id' => $conta->id,
                'nome' => $conta->nome,
                'host' => $conta->host,
                'port' => $conta->port,
                'encryption' => $conta->encryption,
                'username' => $conta->username,
                'pasta' => $conta->pasta,
                'funil_id' => $conta->funil_id,
                'funil_nome' => $conta->funil?->nome,
                'ativo' => $conta->ativo,
                'ultima_captura_em' => $conta->ultima_captura_em?->toIso8601String(),
                'ultimo_status' => $conta->ultimo_status,
                'ultimo_erro' => $conta->ultimo_erro,
            ])->values()->all(),
            'funis' => Funil::query()->orderBy('ordem')->get(['id', 'nome']),
        ]);
    }

    public function store(StoreEmailLeadContaRequest $request): RedirectResponse
    {
        EmailLeadConta::query()->create([
            ...$request->validated(),
            'atualizado_por_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Caixa de e-mail cadastrada.']);

        return redirect()->route('trafego.email.index');
    }

    public function update(UpdateEmailLeadContaRequest $request, EmailLeadConta $conta): RedirectResponse
    {
        $dados = $request->validated();

        // "Deixe em branco para manter": só troca a senha se veio valor novo.
        if (blank($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        $conta->update([...$dados, 'atualizado_por_user_id' => $request->user()?->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Caixa de e-mail atualizada.']);

        return redirect()->route('trafego.email.index');
    }

    public function destroy(EmailLeadConta $conta): RedirectResponse
    {
        $conta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Caixa de e-mail removida.']);

        return redirect()->route('trafego.email.index');
    }

    public function testarConexao(EmailLeadConta $conta, ClientManager $imap): RedirectResponse
    {
        try {
            $imap->make($conta->credenciaisImap())->connect();

            $conta->forceFill(['ultimo_status' => 'ok', 'ultimo_erro' => null])->saveQuietly();

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexão IMAP OK.']);
        } catch (ConnectionFailedException $e) {
            $conta->forceFill(['ultimo_status' => 'erro', 'ultimo_erro' => $e->getMessage()])->saveQuietly();

            Log::channel('leads-email')->warning('Teste de conexão IMAP falhou', [
                'conta_id' => $conta->id,
                'erro' => $e->getMessage(),
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Falha na conexão: '.$e->getMessage()]);
        }

        return redirect()->route('trafego.email.index');
    }
}
```

`StoreEmailLeadContaRequest`/`UpdateEmailLeadContaRequest`: validar `nome`, `host`, `port`
(int, 1–65535), `encryption` (`in:ssl,tls,notls`), `username` (email), `password`
(`required` no store, `nullable` no update), `pasta`, `funil_id` (`exists:funis,id`
escopado por tenant), `finalidade_consentimento_slug` (`exists:finalidades_consentimento,
slug` escopado por tenant — mesma regra de `ValidatesMetaCampanha::regrasCampanha()`),
`ativo` (bool). `password` nunca aparece na resposta (model já tem `#[Hidden]`). O
`index()` do controller passa `finalidades` (mesmo catálogo que `TrafegoController::index()`
já expõe) junto de `funis`, pro `<select>` da tela.

Rotas (`routes/web.php`, dentro do grupo `auth`+`verified` de `/trafego`):

```php
Route::get('trafego/email', [TrafegoEmailController::class, 'index'])->name('trafego.email.index');
Route::post('trafego/email', [TrafegoEmailController::class, 'store'])->name('trafego.email.store');
Route::patch('trafego/email/{conta}', [TrafegoEmailController::class, 'update'])->name('trafego.email.update');
Route::delete('trafego/email/{conta}', [TrafegoEmailController::class, 'destroy'])->name('trafego.email.destroy');
Route::post('trafego/email/{conta}/testar-conexao', [TrafegoEmailController::class, 'testarConexao'])
    ->name('trafego.email.testar-conexao');
```

Nova aba em `resources/js/components/crm/TrafegoTabs.vue` (mesmo padrão das existentes):

```vue
import { index as email } from '@/routes/trafego/email';
// ...
{ label: 'E-mail', href: email(), match: 'crm/TrafegoEmail' },
```

`resources/js/pages/crm/TrafegoEmail.vue`: tabela de contas (nome, host/username mascarado,
funil, status da última captura, ativo) + formulário de criar/editar em modal/drawer (mesmo
componente de formulário reaproveitando o padrão visual das outras telas de `/trafego`) +
botão "Testar conexão" por linha. Campo **Funil** é um `<select>` com as opções de
`funis`, obrigatório — é a resposta direta a "para qual funil vai a lead".

## Passo 5 — Comando `leads:email-capturar`

Itera todos os tenants (mesmo padrão de `SincronizarAnunciosMeta`), e dentro de cada tenant
busca as contas ativas cadastradas na tela acima:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\EmailLeadConta;
use App\Models\EmailLeadProcessado;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

final class CapturarLeadsEmail extends Command
{
    protected $signature = 'leads:email-capturar';

    protected $description = 'Lê as caixas de e-mail cadastradas em /trafego e despacha leads novos para processamento.';

    public function handle(ClientManager $imap, CurrentTenant $currentTenant): int
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($imap, $currentTenant): void {
            $currentTenant->runAs($tenant, function () use ($imap, $tenant): void {
                foreach (EmailLeadConta::query()->ativas()->get() as $conta) {
                    $this->processarConta($imap, $tenant, $conta);
                }
            });
        });

        return self::SUCCESS;
    }

    private function processarConta(ClientManager $imap, Tenant $tenant, EmailLeadConta $conta): void
    {
        try {
            $client = $imap->make($conta->credenciaisImap());
            $client->connect();
            $mensagens = $client->getFolder($conta->pasta)->query()->unseen()->get();
        } catch (ConnectionFailedException $e) {
            $conta->forceFill(['ultimo_status' => 'erro', 'ultimo_erro' => $e->getMessage()])->saveQuietly();

            Log::channel('leads-email')->error('Falha ao conectar caixa de e-mail', [
                'conta_id' => $conta->id,
                'erro' => $e->getMessage(),
            ]);

            return;
        }

        $novos = 0;

        foreach ($mensagens as $mensagem) {
            $messageId = (string) $mensagem->getMessageId();

            if ($messageId === '' || EmailLeadProcessado::query()->where('message_id', $messageId)->exists()) {
                continue;
            }

            EmailLeadProcessado::query()->create([
                'message_id' => $messageId,
                'tenant_id' => $tenant->id,
                'email_lead_conta_id' => $conta->id,
                'status' => 'pendente',
                'payload_html' => $mensagem->getHTMLBody(),
            ]);

            ProcessarLeadEmailRecebido::dispatch(
                messageId: $messageId,
                tenantSlug: $tenant->slug,
                emailLeadContaId: $conta->id,
                html: (string) $mensagem->getHTMLBody(),
            );

            $novos++;
        }

        $conta->forceFill(['ultima_captura_em' => now(), 'ultimo_status' => 'ok', 'ultimo_erro' => null])->saveQuietly();

        Log::channel('leads-email')->info('Captura de leads por e-mail concluída', [
            'conta_id' => $conta->id,
            'novos' => $novos,
        ]);
    }
}
```

A gravação em `email_leads_processados` acontece **antes** do dispatch (com `status`
`pendente`) — isso é o que garante que um segundo poll, mesmo que a mensagem ainda apareça
como `UNSEEN` (falha ao marcar como lida, por exemplo), não dispare o Job de novo: a
checagem `exists()` já barra pelo `message_id`.

## Passo 6 — Agendamento (`routes/console.php`)

```php
Schedule::command('leads:email-capturar')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
```

## Passo 7 — Canal de log (`config/logging.php`)

```php
// Captura de leads por e-mail (IMAP + parser + job). Nunca recebe senha/token.
'leads-email' => [
    'driver' => 'daily',
    'path' => storage_path('logs/leads-email.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
    'replace_placeholders' => true,
],
```

## Critério de aceite

- Tela `/trafego → E-mail` permite criar/editar/remover uma conta, com senha nunca exibida
  de volta e "testar conexão" funcionando contra uma caixa real de sandbox.
- `php artisan leads:email-capturar` roda sem erro, itera os tenants e só processa contas
  `ativo = true` do tenant certo (testar com 2 tenants, cada um com sua própria conta, e
  confirmar isolamento).
- Rodar o comando duas vezes seguidas sem novas mensagens não cria linhas duplicadas nem
  redispara Jobs.
- Teste de feature com `ClientManager` fake/mockado (não bater em IMAP real nos testes)
  cobrindo: e-mail novo → dispatch; e-mail já em `email_leads_processados` → não dispatch;
  falha de conexão → grava `ultimo_status = 'erro'` na conta, log de erro, sem exception não
  tratada.
