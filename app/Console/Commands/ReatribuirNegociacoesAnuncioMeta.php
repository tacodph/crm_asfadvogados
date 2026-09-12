<?php

namespace App\Console\Commands;

use App\Actions\Meta\AtribuirNegociacaoAnuncioMeta;
use App\Models\MetaAdsConta;
use App\Models\Negociacao;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('meta:ads-reatribuir
    {--conta= : ad_account_id — restringe às negociações já ligadas a campanhas dessa conta}
    {--desde= : Data mínima de created_at (Y-m-d) — default 90 dias}
    {--forcar : Reatribui mesmo quem já tem meta_ad_id}
    {--limite=1000 : Máximo de negociações}')]
#[Description('Reprocessa a atribuição Negociacao ↔ anúncio do Meta Ads (após uma sync que trouxe anúncios novos)')]
class ReatribuirNegociacoesAnuncioMeta extends Command
{
    public function handle(CurrentTenant $tenant, AtribuirNegociacaoAnuncioMeta $atribuir): int
    {
        $desde = $this->option('desde') ?: now()->subDays(90)->toDateString();
        $limite = max(1, (int) $this->option('limite'));
        $forcar = (bool) $this->option('forcar');
        $filtroConta = preg_replace('/\D/', '', (string) $this->option('conta')) ?? '';

        $total = 0;
        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $desde, $filtroConta, $forcar, &$total): void {
            $total += $tenant->runAs($t, fn (): int => $this->consulta($desde, $filtroConta, $forcar)->count());
        });

        if ($total === 0) {
            $this->components->info('Nenhuma negociação para reatribuir.');

            return self::SUCCESS;
        }

        $alvo = min($total, $limite);

        if (! $forcar && $alvo > 50 && ! $this->confirm("Reprocessar a atribuição de {$alvo} negociação(ões)?", true)) {
            return self::SUCCESS;
        }

        $feitas = 0;
        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $atribuir, $desde, $filtroConta, $forcar, $limite, &$feitas): bool {
            $tenant->runAs($t, function () use ($atribuir, $desde, $filtroConta, $forcar, $limite, &$feitas): void {
                $this->consulta($desde, $filtroConta, $forcar)
                    ->limit($limite - $feitas)
                    ->get()
                    ->each(function (Negociacao $negociacao) use ($atribuir, $forcar, &$feitas): void {
                        $atribuir($negociacao, $forcar);
                        $feitas++;
                    });
            });

            return $feitas < $limite;
        });

        $this->components->info("{$feitas} negociação(ões) reprocessada(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Negociacao>
     */
    private function consulta(string $desde, string $filtroConta, bool $forcar): Builder
    {
        return Negociacao::query()
            ->where('created_at', '>=', $desde)
            ->when(! $forcar, fn ($q) => $q->whereNull('meta_ad_id'))
            ->when($filtroConta !== '', function ($q) use ($filtroConta): void {
                $conta = MetaAdsConta::query()->where('ad_account_id', $filtroConta)->first();
                $q->whereIn(
                    'meta_campaign_id',
                    $conta?->campanhas()->pluck('meta_campaign_id')->all() ?: ['__nenhum__'],
                );
            })
            ->orderBy('id');
    }
}
