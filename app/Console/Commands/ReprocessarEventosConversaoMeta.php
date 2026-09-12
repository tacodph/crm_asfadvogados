<?php

namespace App\Console\Commands;

use App\Enums\MetaConversaoEventoStatus;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoEvento;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('meta:capi-reprocessar
    {--status=erro : Status dos eventos a reprocessar}
    {--campanha= : Slug da campanha}
    {--desde= : Data mínima de created_at (Y-m-d)}
    {--limite=500 : Máximo de eventos}
    {--force : Não pedir confirmação}')]
#[Description('Recoloca eventos da CAPI na fila de envio (mantém o event_id) — uso operacional após corrigir um token, etc.')]
class ReprocessarEventosConversaoMeta extends Command
{
    public function handle(CurrentTenant $tenant): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $reprocessados = 0;

        // Confirmação uma vez, com base no total que os filtros alcançam.
        $total = 0;
        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, &$total): void {
            $total += $tenant->runAs($t, fn (): int => $this->consultar()->count());
        });

        if ($total === 0) {
            $this->components->info('Nenhum evento corresponde aos filtros.');

            return self::SUCCESS;
        }

        $alvo = min($total, $limite);

        if (! $this->option('force') && ! $this->confirm("Reprocessar {$alvo} de {$total} evento(s)?", true)) {
            return self::SUCCESS;
        }

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $limite, &$reprocessados): bool {
            $tenant->runAs($t, function () use ($limite, &$reprocessados): void {
                $this->consultar()
                    ->limit($limite - $reprocessados)
                    ->get()
                    ->each(function (MetaConversaoEvento $evento) use (&$reprocessados): void {
                        $evento->forceFill(['status' => MetaConversaoEventoStatus::Pendente])->save();
                        EnviarEventoConversaoMeta::dispatch($evento)
                            ->onQueue((string) config('meta.capi.queue'));
                        $reprocessados++;
                    });
            });

            return $reprocessados < $limite;
        });

        $this->components->info("{$reprocessados} evento(s) recolocado(s) na fila.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<MetaConversaoEvento>
     */
    private function consultar(): Builder
    {
        $status = (string) $this->option('status');
        $campanha = $this->option('campanha');
        $desde = $this->option('desde');

        return MetaConversaoEvento::query()
            ->where('status', $status)
            ->when(is_string($campanha) && $campanha !== '', fn (Builder $q) => $q->whereRelation('config', 'slug', $campanha))
            ->when(is_string($desde) && $desde !== '', fn (Builder $q) => $q->where('created_at', '>=', $desde))
            ->orderBy('id');
    }
}
