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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa um lead capturado por e-mail: parseia o HTML, resolve o tenant e
 * a conta (funil/finalidade de consentimento configurados em `/trafego →
 * E-mail`) e delega a persistência para `RegistrarLeadEmail`. Distingue
 * falha permanente (`LeadEmailParseException`, HTML fora do formato — sem
 * retry) de falha transitória (deixa o `$tries`/`$backoff` padrão agir).
 */
final class ProcessarLeadEmailRecebido implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly string $messageId,
        public readonly string $tenantSlug,
        public readonly int $emailLeadContaId,
        public readonly string $html,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping('lead-email-'.$this->messageId)];
    }

    public function handle(ParserLeadEmailHtml $parser, RegistrarLeadEmail $registrar, CurrentTenant $currentTenant): void
    {
        $registro = EmailLeadProcessado::query()->where('message_id', $this->messageId)->first();

        if ($registro === null || $registro->status === 'processado') {
            return; // já processado (ou registro sumiu) — idempotente, nada a fazer.
        }

        $tenant = Tenant::query()->where('slug', $this->tenantSlug)->first();

        if ($tenant === null) {
            $this->marcarErro($registro, sprintf('Tenant "%s" não encontrado.', $this->tenantSlug));

            return; // erro de config, não de dado — sem retry sem intervenção humana.
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
            // A conta só é carregada aqui dentro (tenant-scoped) — nunca antes do runAs.
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
