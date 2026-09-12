<?php

namespace App\Console\Commands;

use App\Actions\Meta\SincronizarInsightsAnunciosMeta;
use App\Models\MetaAdsConta;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:ads-backfill-insights
    {--conta= : ad_account_id (só dígitos)}
    {--desde= : Início da janela (Y-m-d) — obrigatório}
    {--ate= : Fim da janela (Y-m-d) — default hoje}
    {--force : Não pedir confirmação}')]
#[Description('Recarga histórica de meta_ads_insights_diarios para um intervalo (usa o relatório assíncrono quando a janela é grande)')]
class BackfillInsightsAnunciosMeta extends Command
{
    public function handle(CurrentTenant $tenant, SincronizarInsightsAnunciosMeta $sincronizar): int
    {
        $desde = $this->option('desde');

        if (! is_string($desde) || $desde === '') {
            $this->components->error('Informe --desde=Y-m-d.');

            return self::FAILURE;
        }

        try {
            $de = CarbonImmutable::parse($desde)->startOfDay();
            $ate = CarbonImmutable::parse($this->option('ate') ?: 'today')->startOfDay();
        } catch (\Throwable) {
            $this->components->error('Datas inválidas.');

            return self::FAILURE;
        }

        if ($ate->lessThan($de)) {
            $this->components->error('--ate não pode ser antes de --desde.');

            return self::FAILURE;
        }

        $filtro = preg_replace('/\D/', '', (string) $this->option('conta')) ?? '';

        $contar = fn (): int => (int) Tenant::query()->get()->sum(
            fn (Tenant $t): int => $tenant->runAs($t, fn (): int => MetaAdsConta::query()
                ->ativas()
                ->when($filtro !== '', fn ($q) => $q->where('ad_account_id', $filtro))
                ->count()),
        );

        $total = $contar();

        if ($total === 0) {
            $this->components->info('Nenhuma conta ativa para o backfill.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Recarregar insights de {$de->toDateString()} a {$ate->toDateString()} para {$total} conta(s)?",
            true,
        )) {
            return self::SUCCESS;
        }

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $sincronizar, $filtro, $de, $ate): void {
            $tenant->runAs($t, function () use ($sincronizar, $filtro, $de, $ate): void {
                MetaAdsConta::query()
                    ->ativas()
                    ->when($filtro !== '', fn ($q) => $q->where('ad_account_id', $filtro))
                    ->get()
                    ->each(function (MetaAdsConta $conta) use ($sincronizar, $de, $ate): void {
                        $exec = $sincronizar($conta, $de, $ate);
                        $this->components->twoColumnDetail(
                            $conta->nome,
                            "{$exec->status->label()} ({$exec->objetos_afetados} linha(s))",
                        );
                    });
            });
        });

        return self::SUCCESS;
    }
}
