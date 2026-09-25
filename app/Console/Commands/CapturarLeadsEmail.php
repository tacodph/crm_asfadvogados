<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\EmailLeadConta;
use App\Models\EmailLeadProcessado;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

#[Signature('leads:email-capturar')]
#[Description('Lê as caixas de e-mail cadastradas em /trafego → E-mail e despacha leads novos para processamento')]
class CapturarLeadsEmail extends Command
{
    public function handle(ClientManager $imap, CurrentTenant $currentTenant): int
    {
        $totalNovos = 0;
        $totalContas = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($imap, $currentTenant, &$totalNovos, &$totalContas): void {
            $currentTenant->runAs($tenant, function () use ($imap, $tenant, &$totalNovos, &$totalContas): void {
                EmailLeadConta::query()->ativas()->get()->each(function (EmailLeadConta $conta) use ($imap, $tenant, &$totalNovos, &$totalContas): void {
                    $totalContas++;
                    $totalNovos += $this->processarConta($imap, $tenant, $conta);
                });
            });
        });

        $this->components->info(
            $totalContas === 0
                ? 'Nenhuma caixa de e-mail ativa cadastrada.'
                : "{$totalContas} caixa(s) verificada(s), {$totalNovos} lead(s) novo(s).",
        );

        return self::SUCCESS;
    }

    private function processarConta(ClientManager $imap, Tenant $tenant, EmailLeadConta $conta): int
    {
        try {
            $client = $imap->make($conta->credenciaisImap());
            $client->connect();
            $mensagens = $client->getFolder($conta->pasta)->query()->whereUnseen()->get();
        } catch (ConnectionFailedException $e) {
            $conta->forceFill(['ultimo_status' => 'erro', 'ultimo_erro' => $e->getMessage()])->saveQuietly();

            Log::channel('leads-email')->error('Falha ao conectar caixa de e-mail', [
                'conta_id' => $conta->id,
                'erro' => $e->getMessage(),
            ]);

            $this->components->twoColumnDetail($conta->nome, '<error>falha de conexão</error>');

            return 0;
        }

        $novos = 0;

        foreach ($mensagens as $mensagem) {
            $messageId = trim((string) $mensagem->getMessageId());

            if ($messageId === '' || EmailLeadProcessado::query()->where('message_id', $messageId)->exists()) {
                continue;
            }

            $html = (string) $mensagem->getHTMLBody();

            EmailLeadProcessado::query()->create([
                'message_id' => $messageId,
                'tenant_id' => $tenant->id,
                'email_lead_conta_id' => $conta->id,
                'status' => 'pendente',
                'payload_html' => $html,
            ]);

            ProcessarLeadEmailRecebido::dispatch(
                messageId: $messageId,
                tenantSlug: $tenant->slug,
                emailLeadContaId: $conta->id,
                html: $html,
            );

            $novos++;
        }

        $conta->forceFill(['ultima_captura_em' => now(), 'ultimo_status' => 'ok', 'ultimo_erro' => null])->saveQuietly();

        $this->components->twoColumnDetail($conta->nome, "{$novos} novo(s)");

        return $novos;
    }
}
