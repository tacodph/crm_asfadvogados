<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\EmailLeadProcessado;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leads:email-reprocessar {message_id : Message-ID do e-mail a reprocessar}')]
#[Description('Reprocessa um lead de e-mail que falhou, usando o HTML já salvo (sem buscar o e-mail de novo no IMAP)')]
class ReprocessarLeadEmail extends Command
{
    public function handle(): int
    {
        $messageId = (string) $this->argument('message_id');

        $registro = EmailLeadProcessado::query()->where('message_id', $messageId)->first();

        if ($registro === null) {
            $this->components->error(sprintf('Nenhum registro encontrado para message_id "%s".', $messageId));

            return self::FAILURE;
        }

        if ($registro->payload_html === null || $registro->email_lead_conta_id === null) {
            $this->components->error('Registro não tem payload_html/conta salvos — não é possível reprocessar sem buscar o e-mail de novo.');

            return self::FAILURE;
        }

        $tenantSlug = $registro->tenant?->slug;

        if ($tenantSlug === null) {
            $this->components->error('Não foi possível determinar o tenant para reprocessamento.');

            return self::FAILURE;
        }

        $registro->forceFill(['status' => 'pendente', 'erro' => null])->save();

        ProcessarLeadEmailRecebido::dispatch(
            messageId: $registro->message_id,
            tenantSlug: $tenantSlug,
            emailLeadContaId: $registro->email_lead_conta_id,
            html: $registro->payload_html,
        );

        $this->components->info(sprintf('Reprocessamento de "%s" disparado.', $messageId));

        return self::SUCCESS;
    }
}
