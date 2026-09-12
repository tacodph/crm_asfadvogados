<?php

namespace App\Console\Commands;

use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsInsightDiario;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:ads-expurgar-brutos')]
#[Description('Retenção: zera bruto/acoes de meta_ads_insights_diarios e bruto da estrutura mais velhos que meta.capi.retencao_dias (preserva os agregados numéricos)')]
class ExpurgarBrutosAnunciosMeta extends Command
{
    public function handle(CurrentTenant $tenant): int
    {
        $dias = (int) config('meta.capi.retencao_dias', 180);
        $limite = now()->subDays($dias);
        $afetados = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $limite, &$afetados): void {
            $tenant->runAs($t, function () use ($limite, &$afetados): void {
                MetaAdsInsightDiario::query()
                    ->where('created_at', '<', $limite)
                    ->where(fn ($q) => $q->where('bruto', '!=', '[]')->orWhereNotNull('acoes'))
                    ->chunkById(500, function ($linhas) use (&$afetados): void {
                        foreach ($linhas as $linha) {
                            $linha->update(['bruto' => [], 'acoes' => null]);
                            $afetados++;
                        }
                    });

                foreach ([MetaAdsCampanha::class, MetaAdsConjunto::class, MetaAdsAnuncio::class] as $model) {
                    $model::query()
                        ->where('created_at', '<', $limite)
                        ->where('bruto', '!=', '[]')
                        ->chunkById(500, function ($linhas) use (&$afetados): void {
                            foreach ($linhas as $linha) {
                                $linha->update(['bruto' => []]);
                                $afetados++;
                            }
                        });
                }
            });
        });

        $this->components->info("{$afetados} registro(s) tiveram o payload cru expurgado (retenção: {$dias} dias).");

        return self::SUCCESS;
    }
}
