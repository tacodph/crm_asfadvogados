# Prompt 04 — Hardening, reprocessamento e runbook

## Objetivo

Fechar a série: garantir que erro de parsing não vira loop de retry infinito, dar um jeito
operacional de reprocessar um e-mail depois de corrigir um bug, e documentar o runbook.

Pré-requisitos: prompts 01–03 executados e testados.

## 1. Retry: permanente vs transitório (checagem final)

Já coberto no desenho do Job (prompt 03), mas confirmar nesta etapa com um teste dedicado
que reforça o contrato:

- `LeadEmailParseException` (HTML corrompido, campo faltante, valor não parseável) →
  `email_leads_processados.status = 'erro'`, Job **não** relança a exceção → não conta
  como tentativa fracassada, não há novo `attempt` agendado pelo `$backoff`.
- Qualquer outra `Throwable` (erro de banco, bug de código, tenant sumiu no meio do
  caminho) → deixar propagar normalmente, para o mecanismo padrão de `$tries`/`$backoff`
  do Laravel agir; só o método `failed()` grava o estado final depois de esgotar as
  tentativas.

Teste: `tests/Feature/LeadsEmail/RetryLeadEmailTest.php` — simular uma falha transitória
(ex. mock do `RegistrarLeadEmail` lançando `RuntimeException` genérica) e verificar que o
Job é liberado para nova tentativa (`Queue::assertPushed` + contagem de `attempts()`), ao
contrário do caso de `LeadEmailParseException`.

## 2. Comando de reprocessamento manual — `leads:email-reprocessar`

Útil quando um e-mail falhou por um bug do parser que já foi corrigido — reaproveita o
`payload_html` já salvo em `email_leads_processados`, sem precisar ir buscar a mensagem de
novo no IMAP.

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\EmailLeadProcessado;
use Illuminate\Console\Command;

final class ReprocessarLeadEmail extends Command
{
    protected $signature = 'leads:email-reprocessar {message_id}';

    protected $description = 'Reprocessa um lead de e-mail que falhou, usando o HTML já salvo.';

    public function handle(): int
    {
        $messageId = (string) $this->argument('message_id');

        $registro = EmailLeadProcessado::query()->where('message_id', $messageId)->first();

        if ($registro === null) {
            $this->error(sprintf('Nenhum registro encontrado para message_id "%s".', $messageId));

            return self::FAILURE;
        }

        if ($registro->payload_html === null || $registro->email_lead_conta_id === null) {
            $this->error('Registro não tem payload_html/conta salvos — não é possível reprocessar sem buscar o e-mail de novo.');

            return self::FAILURE;
        }

        $tenantSlug = $registro->tenant?->slug;

        if ($tenantSlug === null) {
            $this->error('Não foi possível determinar o tenant para reprocessamento.');

            return self::FAILURE;
        }

        $registro->forceFill(['status' => 'pendente', 'erro' => null])->save();

        ProcessarLeadEmailRecebido::dispatch(
            messageId: $registro->message_id,
            tenantSlug: $tenantSlug,
            emailLeadContaId: $registro->email_lead_conta_id,
            html: $registro->payload_html,
        );

        $this->info(sprintf('Reprocessamento de "%s" disparado.', $messageId));

        return self::SUCCESS;
    }
}
```

`email_lead_conta_id` (gravado pelo comando de captura, prompt 01) é o que permite achar o
funil certo no reprocessamento mesmo que a `EmailLeadConta` tenha sido editada depois — sem
precisar de nenhum fallback de config, já que a configuração inteira mora no banco desde o
prompt 01 (tela `/trafego → E-mail`).

Testes: `tests/Feature/LeadsEmail/ReprocessarLeadEmailTest.php` cobrindo message_id
inexistente, registro sem `payload_html`, e reprocessamento bem-sucedido (dispatch do Job
com o HTML certo).

## 3. Runbook (a criar nesta etapa: `documents/automacao_email_gmail/runbook.md`)

Seguir o formato de `documents/api_meta/runbook_capi.md` (mapa rápido + um cabeçalho por
sintoma). Conteúdo mínimo:

```markdown
# Runbook — Captação de leads por e-mail

## Mapa rápido
- Captura roda a cada 5 min via `leads:email-capturar` (`routes/console.php`).
- Estado de cada e-mail: tabela `email_leads_processados` (`status`: pendente/processado/erro).
- Log dedicado: `storage/logs/leads-email-*.log` (canal `leads-email`).

## IMAP não conecta
- Rodar `php artisan leads:email-capturar` manualmente e ler o log `leads-email` —
  a exceção de conexão vem com a mensagem original do `webklex/laravel-imap`. A conta
  também grava `ultimo_status`/`ultimo_erro` (visíveis na tela `/trafego → E-mail`).
- Usar o botão "Testar conexão" na tela `/trafego → E-mail` para isolar se é problema de
  credencial (senha de app revogada/expirada) ou de rede.
- Confirmar que a senha cadastrada ainda é uma senha de app válida (senhas de app podem
  ser revogadas manualmente no Google Workspace, ou automaticamente se a senha da conta
  mudar) — reeditar a conta com a senha nova.
- Confirmar que o admin do Workspace não desativou "senhas de app" para a organização —
  se isso aconteceu, revisar a decisão de XOAUTH2 vs API do Gmail em
  `01_estrategia_captura_imap.md`.

## E-mails não viram lead
- Checar `email_leads_processados` filtrando por `status = 'erro'` — a coluna `erro`
  tem a mensagem exata (parsing ou persistência).
- Se `erro` cita `LeadEmailParseException` (campo ausente/valor inválido), o formato do
  e-mail mudou — inspecionar `payload_html` do registro para ver a tabela real recebida.

## Parser falhando por mudança de formato do e-mail
- Ajustar `App\Support\LeadsEmail\ParserLeadEmailHtml` (rótulos aceitos,
  `parseTipoPessoa`, `parseValorMonetario`).
- Depois do fix, reprocessar os e-mails que falharam:
  `php artisan leads:email-reprocessar {message_id}` para cada `message_id` com
  `status = 'erro'`.

## Reprocessar em massa
- `EmailLeadProcessado::where('status', 'erro')->pluck('message_id')` e rodar
  `leads:email-reprocessar` para cada um (ou um comando auxiliar `--todos-com-erro`, se o
  volume justificar — ainda não implementado).
```

## 4. Nota de expansão SaaS (documentar no runbook, não implementar agora)

Como a configuração já mora no banco (`email_leads_contas`, tela `/trafego → E-mail`) desde
o prompt 01, virar SaaS não exige nenhuma mudança de código: o dia em que existir mais de
um tenant, cada um cadastra sua própria caixa de e-mail e escolhe seu próprio funil pela
mesma tela — o comando `leads:email-capturar` já itera todos os tenants e todas as contas
ativas de cada um. A única coisa a revisar nesse momento é a UX/permissão da tela (garantir
que um tenant não vê a conta de outro — já garantido pelo `BelongsToTenant`/`TenantScope`,
mas vale um teste de isolamento dedicado antes de abrir para o segundo cliente).

## Critério de aceite

- Teste de retry (item 1) verde, provando que erro de parsing não gera retry.
- Comando `leads:email-reprocessar` funcional com os 3 testes descritos.
- `documents/automacao_email_gmail/runbook.md` criado com o conteúdo acima (ajustado ao
  que realmente foi implementado nos prompts 01–03, se algo tiver mudado no caminho).
