# Prompt 03 — Job, Action e persistência (Contato / Empresa / Negociação)

## Objetivo

Processar em background um lead já capturado (prompt 01) e parseado (prompt 02),
persistindo-o no mesmo modelo de dados que o endpoint de tráfego já usa, e abrindo a
`Negociacao` **no funil escolhido pela equipe de tráfego** ao cadastrar a conta de e-mail
(`EmailLeadConta::$funil_id`, tela `/trafego → E-mail` do prompt 01).

Pré-requisito: antes de escrever a Action, **abrir e ler por completo**
`app/Http/Controllers/CapturarLeadTrafegoController.php` e
`app/Actions/Crm/FindContatoDuplicatas.php` no repo — este prompt descreve o desenho a
seguir, mas as assinaturas exatas de métodos como o dedup e o registro de consentimento
devem ser copiadas do código real, não da paráfrase abaixo (o objetivo é reusar a mesma
lógica, não escrever uma segunda versão divergente).

## Job — `App\Jobs\ProcessarLeadEmailRecebido`

Estilo de `App\Jobs\EnviarEventoConversaoMeta` (tries/backoff/`WithoutOverlapping`/log
dedicado), adaptado para diferenciar **falha permanente** (HTML fora do formato →
`LeadEmailParseException`, não adianta tentar de novo) de **falha transitória** (erro de
banco, exceção inesperada → vale retry).

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\LeadsEmail\RegistrarLeadEmail;
use App\Models\EmailLeadConta;
use App\Models\EmailLeadProcessado;
use App\Models\Tenant;
use App\Support\LeadsEmail\Exceptions\LeadEmailParseException;
use App\Support\LeadsEmail\ParserLeadEmailHtml;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessarLeadEmailRecebido implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly string $messageId,
        public readonly string $tenantSlug,
        public readonly int $emailLeadContaId,
        public readonly string $html,
    ) {
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping('lead-email-'.$this->messageId)];
    }

    public function handle(ParserLeadEmailHtml $parser, RegistrarLeadEmail $registrar, CurrentTenant $currentTenant): void
    {
        $registro = EmailLeadProcessado::query()->where('message_id', $this->messageId)->first();

        if ($registro === null || $registro->status === 'processado') {
            return; // já processado (ou registro sumiu) — nada a fazer, idempotente.
        }

        $tenant = Tenant::query()->where('slug', $this->tenantSlug)->first();

        if ($tenant === null) {
            $this->marcarErro($registro, sprintf('Tenant "%s" não encontrado.', $this->tenantSlug));

            return; // erro de config, não de dado — não faz sentido dar retry sem intervenção humana.
        }

        try {
            $dados = $parser->parse($this->html);
        } catch (LeadEmailParseException $e) {
            Log::channel('leads-email')->warning('Falha ao parsear lead de e-mail', [
                'message_id' => $this->messageId,
                'erro' => $e->getMessage(),
            ]);

            $this->marcarErro($registro, $e->getMessage());

            return; // falha permanente: HTML fora do formato. Sem retry.
        }

        $currentTenant->runAs($tenant, function () use ($registrar, $registro, $dados, $tenant): void {
            // Dentro do runAs a query já vem tenant-scoped — carrega a conta aqui,
            // não antes, para pegar o funil configurado no momento certo.
            $conta = EmailLeadConta::query()->findOrFail($this->emailLeadContaId);

            $resultado = $registrar($dados, $conta);

            $registro->forceFill([
                'tenant_id' => $tenant->id,
                'contato_id' => $resultado->contato->id,
                'negociacao_id' => $resultado->negociacao->id,
                'status' => 'processado',
                'erro' => null,
            ])->save();
        });

        Log::channel('leads-email')->info('Lead de e-mail processado', [
            'message_id' => $this->messageId,
            'tenant' => $this->tenantSlug,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $registro = EmailLeadProcessado::query()->where('message_id', $this->messageId)->first();

        $registro?->forceFill([
            'status' => 'erro',
            'erro' => $e->getMessage(),
        ])->saveQuietly();

        Log::channel('leads-email')->error('Job de lead de e-mail falhou definitivamente', [
            'message_id' => $this->messageId,
            'erro' => $e->getMessage(),
        ]);
    }

    private function marcarErro(EmailLeadProcessado $registro, string $mensagem): void
    {
        $registro->forceFill(['status' => 'erro', 'erro' => $mensagem])->save();
    }
}
```

Nota sobre `CurrentTenant`: jobs em fila são serializados e reidratados por um worker que
pode não ter nenhum tenant "atual" — por isso o tenant é resolvido pelo `tenantSlug`
(propriedade pública serializável do Job) **dentro** do `handle()`, nunca antes do
`dispatch()`. Pela mesma razão, `EmailLeadConta` só é carregada **dentro** do `runAs()`.

## Action — `App\Actions\LeadsEmail\RegistrarLeadEmail`

Mesma responsabilidade das três etapas do `CapturarLeadTrafegoController`
(`resolverContato()` → `registrarConsentimento()` → `abrirNegociacao()`), só que a origem
dos dados é o `LeadEmailDados` do parser em vez de um `FormRequest` validado, e o **funil é
recebido como parâmetro** (o da `EmailLeadConta` que capturou o e-mail) em vez de resolvido
por slug/default como no controller do site. Ao implementar, **copiar a lógica dessas três
etapas do controller real**, ajustando apenas:

- `resolverContato()`: usa `FindContatoDuplicatas` com `email`/`telefone` de
  `LeadEmailDados` (o e-mail não traz CPF, então não há critério de CPF aqui). Ao criar um
  contato novo, `tipo_pessoa_id` vem do slug `pf`/`pj` de `LeadEmailDados::$tipoPessoa`
  (`TipoPessoa::query()->where('slug', $dados->tipoPessoa)->value('id')`, mesmo padrão do
  controller). **Não criar `Empresa`** mesmo quando `tipoPessoa === 'pj'` — o e-mail não
  traz CNPJ, então não há dado suficiente para abrir/casar uma empresa; deixar
  `empresa_id` nulo (decisão registrada no README da série).
- `registrarConsentimento()`: mesma finalidade de marketing usada no endpoint de tráfego —
  copiar sem alteração.
- `abrirNegociacao()`: não resolve funil por slug — recebe a `EmailLeadConta $conta` (que
  carrega `funil_id` e `finalidade_consentimento_slug`, ambos escolhidos pela equipe de
  tráfego na tela `/trafego → E-mail`) e usa a primeira etapa do funil dela
  (`$conta->funil()->firstOrFail()->etapas()->orderBy('ordem')->firstOrFail()`, igual ao
  controller do site). `registrarConsentimento()` também recebe a finalidade da própria
  `$conta`, não um valor fixo — por isso a Action toma `EmailLeadConta $conta` inteira como
  parâmetro (não um `Funil` avulso): evita passar dois parâmetros relacionados separados.
  `valor` = `LeadEmailDados::$valorDivida`; `origem_utm` monta:

  ```php
  $origemUtm = [
      'fonte' => 'email',
      'utm_campaign' => $dados->campanha,
      'campanha_nome' => $dados->campanha,
      'conjunto_nome' => $dados->conjunto,
      'anuncio_nome' => $dados->anuncio,
  ];
  ```

  `utm_campaign` é preenchido com o nome da campanha porque
  `AtribuirNegociacaoAnuncioMeta` tenta casar `utm_campaign` contra `MetaAdsCampanha.nome`
  (comparação case-insensitive) — se a campanha do e-mail tiver o mesmo nome cadastrado na
  Meta Ads, a atribuição automática ainda funciona; se não casar, o campo fica só como
  metadado informativo, sem erro.

Estrutura da Action (assinatura e retorno — corpo a copiar do controller):

```php
<?php

declare(strict_types=1);

namespace App\Actions\LeadsEmail;

use App\Models\Contato;
use App\Models\EmailLeadConta;
use App\Models\Negociacao;
use App\Support\LeadsEmail\LeadEmailDados;
use Illuminate\Support\Facades\DB;

final readonly class RegistrarLeadEmailResultado
{
    public function __construct(
        public Contato $contato,
        public Negociacao $negociacao,
    ) {
    }
}

final class RegistrarLeadEmail
{
    public function __invoke(LeadEmailDados $dados, EmailLeadConta $conta): RegistrarLeadEmailResultado
    {
        return DB::transaction(function () use ($dados, $conta): RegistrarLeadEmailResultado {
            $contato = $this->resolverContato($dados);
            $this->registrarConsentimento($contato, $conta->finalidade_consentimento_slug);
            $negociacao = $this->abrirNegociacao($contato, $dados, $conta);

            return new RegistrarLeadEmailResultado($contato, $negociacao);
        });
    }

    // resolverContato/registrarConsentimento/abrirNegociacao: copiar do
    // CapturarLeadTrafegoController, com os ajustes descritos acima. Precisa também dos
    // helpers privados canalId()/statusConsentimentoConcedidoId()/statusComercialId() do
    // controller (canal slug 'email' em vez de 'site').
}
```

Rodando dentro de `CurrentTenant::runAs()` (feito pelo Job), o hook `creating` do trait
`BelongsToTenant` já grava o `tenant_id` certo em `Contato`/`Negociacao` — a Action não
precisa (nem deve) setar `tenant_id` manualmente.

`NegociacaoObserver::created` dispara sozinho a atribuição Meta e o evento CAPI `Lead` —
não chamar nada disso manualmente aqui, mesmo comportamento do endpoint de tráfego.

## Testes de feature (`tests/Feature/LeadsEmail/ProcessarLeadEmailRecebidoTest.php`)

1. **Lead novo**: dispara o Job com um `LeadEmailDados` de contato inexistente e uma
   `EmailLeadConta` configurada com o Funil X → cria `Contato` (com `tipo_pessoa_id`
   correto) e `Negociacao` **na primeira etapa do Funil X** (valor e `origem_utm`
   corretos); `email_leads_processados.status` vira `processado` com
   `contato_id`/`negociacao_id` preenchidos.
2. **Duas contas de e-mail com funis diferentes**: leads capturados por cada conta abrem
   negociação no funil configurado naquela conta especificamente — prova que o funil não é
   fixo/global.
3. **Contato já existente** (mesmo e-mail): não duplica `Contato` (atualiza campos vazios
   se aplicável, mesmo comportamento do dedup do endpoint de tráfego), mas **cria uma nova
   `Negociacao`**.
4. **Reprocessamento do mesmo `message_id`** (Job disparado duas vezes para o mesmo
   registro já `processado`): segunda execução é no-op (não cria segunda negociação) —
   cobre o guard no início do `handle()`.
5. **Tenant inexistente**: `email_leads_processados.status` vira `erro` com mensagem
   citando o slug, sem lançar exception não tratada.
6. **HTML corrompido chega até o Job** (parser lança `LeadEmailParseException`): status
   vira `erro` com a mensagem do parser, e o Job **não** entra em retry (verificar que
   `release()`/nova tentativa não é agendada — o método retorna normalmente após marcar
   erro, não relança a exceção).

## Critério de aceite

- Os 6 casos de teste acima verdes.
- Nenhuma duplicata de `Negociacao` em reprocessamento do mesmo `message_id`.
- `Contato`/`Negociacao` criados sempre com o `tenant_id` do tenant resolvido pelo slug, e a
  `Negociacao` sempre no funil da `EmailLeadConta` que originou o lead — testar
  explicitamente com 2 tenants (cada um com sua conta e funil) para garantir que o
  `CurrentTenant::runAs()` está isolando corretamente.
