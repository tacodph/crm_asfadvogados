# Runbook — Captação de leads por e-mail

## Mapa rápido

- Configuração (caixas de e-mail, funil, finalidade de consentimento): tela
  `/trafego → E-mail` (`TrafegoEmailController`, tabela `email_leads_contas`).
- Captura roda a cada 5 min via `leads:email-capturar` (`routes/console.php`).
- Estado de cada e-mail: tabela `email_leads_processados`
  (`status`: `pendente`/`processado`/`erro`).
- Log dedicado: `storage/logs/leads-email-*.log` (canal `leads-email`).

## IMAP não conecta

- Usar o botão **"Testar conexão"** na tela `/trafego → E-mail` — grava
  `ultimo_status`/`ultimo_erro` na própria conta e mostra a mensagem de erro na hora.
- Rodar `php artisan leads:email-capturar` manualmente e ler o log `leads-email` para a
  mesma mensagem original do `webklex/laravel-imap`.
- Confirmar que a senha cadastrada ainda é uma **senha de app** válida do Google Workspace
  (senhas de app podem ser revogadas manualmente, ou automaticamente se a senha da conta
  Google mudar) — reeditar a conta com a senha nova (campo fica em branco = mantém a
  atual; só troca se algo for digitado).
- Confirmar que o admin do Workspace não desativou "senhas de app" para a organização — se
  isso aconteceu, revisar a decisão de XOAUTH2 vs API do Gmail em
  `01_estrategia_captura_imap.md`.

## E-mails não viram lead

- Checar `email_leads_processados` filtrando por `status = 'erro'` — a coluna `erro` tem a
  mensagem exata (parsing ou persistência).
- Se `erro` cita "Campo obrigatório" ou "Valor inválido" (mensagens de
  `LeadEmailParseException`), o formato do e-mail mudou — inspecionar a coluna
  `payload_html` do registro pra ver a tabela real recebida.
- Se `erro` cita `Tenant "..." não encontrado`, o `slug` do tenant mudou ou a conta ficou
  órfã — conferir `email_leads_contas.tenant_id` / `tenants.slug`.

## Parser falhando por mudança de formato do e-mail

- Ajustar `App\Support\LeadsEmail\ParserLeadEmailHtml` (rótulos aceitos em
  `CAMPOS_OBRIGATORIOS`, `parseTipoPessoa()`, `parseValorMonetario()`, `parseData()`).
- Depois do fix, reprocessar os e-mails que falharam:
  `php artisan leads:email-reprocessar "<message-id>"` para cada `message_id` com
  `status = 'erro'` (o comando reusa o `payload_html` já salvo, não busca o e-mail de novo
  no IMAP).

## Reprocessar em massa

```php
App\Models\EmailLeadProcessado::where('status', 'erro')->pluck('message_id')
    ->each(fn ($id) => \Illuminate\Support\Facades\Artisan::call('leads:email-reprocessar', ['message_id' => $id]));
```

Rodar via `php artisan tinker` (ou um comando auxiliar dedicado, se o volume crescer o
bastante pra justificar um `--todos-com-erro` — ainda não implementado).

## Fila parada

Os leads são processados por `App\Jobs\ProcessarLeadEmailRecebido` na fila padrão da
aplicação — se a fila estiver parada, os registros ficam em `status = 'pendente'`
indefinidamente (a captura já gravou `email_leads_processados`, só falta o worker rodar).
Confirmar `queue:work`/`schedule:work` de pé antes de investigar qualquer outra causa.

## Expansão para SaaS (mais de um tenant)

Como a configuração já mora no banco (`email_leads_contas`), cada tenant novo só precisa
cadastrar sua própria caixa de e-mail e escolher seu próprio funil/finalidade pela mesma
tela — o comando `leads:email-capturar` já itera todos os tenants e todas as contas ativas
de cada um (`Tenant::query()->each(...)` + `CurrentTenant::runAs`). Nenhuma mudança de
código é necessária; validar apenas que um tenant não vê a conta de outro (isolamento já
garantido por `BelongsToTenant`/`TenantScope`, mas vale um teste de isolamento dedicado
antes de abrir para o segundo cliente).
