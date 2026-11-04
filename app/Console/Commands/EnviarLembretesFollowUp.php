<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Crm\LembrarTarefasDoDia;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:lembretes-follow-up')]
#[Description('Envia ao sino do CRM os lembretes das tarefas de hoje que chegaram no horário')]
class EnviarLembretesFollowUp extends Command
{
    public function handle(CurrentTenant $currentTenant, LembrarTarefasDoDia $lembrar): int
    {
        $agora = now(config('crm.timezone'));
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($currentTenant, $lembrar, $agora, &$total): void {
            $total += $currentTenant->runAs($tenant, fn (): int => $lembrar($agora));
        });

        $this->components->info("{$total} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
