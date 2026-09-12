<?php

namespace App\Console\Commands;

use App\Actions\Meta\SincronizarEstruturaAnunciosMeta;
use App\Actions\Meta\SincronizarInsightsAnunciosMeta;
use App\Jobs\SincronizarEstruturaAnunciosMetaJob;
use App\Jobs\SincronizarInsightsAnunciosMetaJob;
use App\Models\MetaAdsConta;
use App\Models\Tenant;
use App\Support\Meta\MarketingApiClient;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

#[Signature('meta:ads-sincronizar
    {--conta= : ad_account_id (só dígitos) de uma conta específica}
    {--tipo=tudo : estrutura|insights|tudo}
    {--dias= : janela de insights em dias (default config meta.ads.reprocessar_dias)}
    {--sync : roda as Actions inline, sem enfileirar}')]
#[Description('Sincroniza estrutura e insights do Meta Ads de cada conta ativa (por tenant)')]
class SincronizarAnunciosMeta extends Command
{
    public function handle(CurrentTenant $tenant, MarketingApiClient $client): int
    {
        $filtro = preg_replace('/\D/', '', (string) $this->option('conta')) ?? '';
        $tipo = (string) $this->option('tipo');
        $dias = $this->option('dias') !== null
            ? max(1, (int) $this->option('dias'))
            : (int) config('meta.ads.reprocessar_dias', 28);

        $contas = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $client, $filtro, $tipo, $dias, &$contas): void {
            $tenant->runAs($t, function () use ($client, $filtro, $tipo, $dias, &$contas): void {
                MetaAdsConta::query()
                    ->ativas()
                    ->when($filtro !== '', fn ($q) => $q->where('ad_account_id', $filtro))
                    ->get()
                    ->each(function (MetaAdsConta $conta) use ($client, $tipo, $dias, &$contas): void {
                        $contas++;

                        if (! $client->registrarVerificacao($conta)->ok) {
                            $this->components->twoColumnDetail(
                                $conta->nome,
                                '<comment>token inválido / sem ads_read — pulada</comment>',
                            );

                            return;
                        }

                        $this->sincronizar($conta, $tipo, $dias);
                    });
            });
        });

        $this->components->info($contas === 0
            ? 'Nenhuma conta de anúncio ativa.'
            : "{$contas} conta(s) processada(s).");

        return self::SUCCESS;
    }

    private function sincronizar(MetaAdsConta $conta, string $tipo, int $dias): void
    {
        $ate = CarbonImmutable::now()->startOfDay();
        $de = $ate->subDays($dias - 1);

        $fazEstrutura = in_array($tipo, ['estrutura', 'tudo'], true);
        $fazInsights = in_array($tipo, ['insights', 'tudo'], true);

        if ($this->option('sync')) {
            if ($fazEstrutura) {
                $e = app(SincronizarEstruturaAnunciosMeta::class)($conta);
                $this->components->twoColumnDetail("{$conta->nome} · estrutura", "{$e->status->label()} ({$e->objetos_afetados})");
            }

            if ($fazInsights) {
                $i = app(SincronizarInsightsAnunciosMeta::class)($conta, $de, $ate);
                $this->components->twoColumnDetail("{$conta->nome} · insights", "{$i->status->label()} ({$i->objetos_afetados})");
            }

            return;
        }

        $chain = [];

        if ($fazEstrutura) {
            $chain[] = new SincronizarEstruturaAnunciosMetaJob($conta);
        }

        if ($fazInsights) {
            $chain[] = new SincronizarInsightsAnunciosMetaJob($conta, $de->toDateString(), $ate->toDateString());
        }

        Bus::chain($chain)->onQueue((string) config('meta.ads.queue'))->dispatch();

        $this->components->twoColumnDetail($conta->nome, 'sincronização enfileirada');
    }
}
