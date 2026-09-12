<?php

namespace App\Console\Commands;

use App\Enums\MetaConversaoEventoStatus;
use App\Models\MetaConversaoEvento;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:capi-expurgar-payloads')]
#[Description('LGPD: zera request_payload/response_body de eventos enviados mais velhos que meta.capi.retencao_dias (mantém fbtrace_id, status e contadores)')]
class ExpurgarPayloadsConversaoMeta extends Command
{
    public function handle(CurrentTenant $tenant): int
    {
        $dias = (int) config('meta.capi.retencao_dias', 180);
        $limite = now()->subDays($dias);
        $afetados = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $limite, &$afetados): void {
            $tenant->runAs($t, function () use ($limite, &$afetados): void {
                MetaConversaoEvento::query()
                    ->where('status', MetaConversaoEventoStatus::Enviado->value)
                    ->where('created_at', '<', $limite)
                    ->where(fn ($q) => $q->where('request_payload', '!=', '[]')->orWhereNotNull('response_body'))
                    ->chunkById(500, function ($eventos) use (&$afetados): void {
                        foreach ($eventos as $evento) {
                            $evento->update(['request_payload' => [], 'response_body' => null]);
                            $afetados++;
                        }
                    });
            });
        });

        $this->components->info("{$afetados} evento(s) tiveram os payloads expurgados (retenção: {$dias} dias).");

        return self::SUCCESS;
    }
}
