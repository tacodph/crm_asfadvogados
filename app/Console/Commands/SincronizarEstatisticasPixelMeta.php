<?php

namespace App\Console\Commands;

use App\Actions\Meta\SincronizarEstatisticasPixelMeta as SincronizarAction;
use App\Models\MetaConversaoConfig;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:sincronizar-estatisticas {--campanha= : Slug de uma campanha específica}')]
#[Description('Grava o snapshot diário do Pixel (last_fired_time + /stats) de cada campanha ativa da CAPI')]
class SincronizarEstatisticasPixelMeta extends Command
{
    public function handle(CurrentTenant $tenant, SincronizarAction $sincronizar): int
    {
        $slug = $this->option('campanha');
        $total = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $sincronizar, $slug, &$total): void {
            $tenant->runAs($t, function () use ($sincronizar, $slug, &$total): void {
                MetaConversaoConfig::query()
                    ->ativas()
                    ->when(is_string($slug) && $slug !== '', fn ($q) => $q->where('slug', $slug))
                    ->get()
                    ->each(function (MetaConversaoConfig $config) use ($sincronizar, &$total): void {
                        $estatistica = $sincronizar($config);
                        $total++;
                        $this->components->twoColumnDetail(
                            $config->slug,
                            $estatistica->pixel_last_fired_at?->diffForHumans() ?? 'sem last_fired_time',
                        );
                    });
            });
        });

        $this->components->info($total === 0
            ? 'Nenhuma campanha ativa para sincronizar.'
            : "{$total} campanha(s) sincronizada(s).");

        return self::SUCCESS;
    }
}
