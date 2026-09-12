<?php

namespace App\Jobs;

use App\Actions\Meta\SincronizarEstruturaAnunciosMeta;
use App\Enums\MetaAdsSyncStatus;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsSyncExecucao;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Sincroniza a estrutura de anúncios de uma conta. Em rodada `parcial`
 * (rate limit) o job se re-agenda; a próxima passada completa.
 */
class SincronizarEstruturaAnunciosMetaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [300, 900, 3600];

    public function __construct(public MetaAdsConta $conta) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('meta-ads-estrutura-'.$this->conta->id)];
    }

    public function handle(SincronizarEstruturaAnunciosMeta $sincronizar): void
    {
        $execucao = $sincronizar($this->conta);

        if ($execucao->status === MetaAdsSyncStatus::Parcial) {
            // ponytail: recuo fixo de 15min. Threading do `esperarSegundos` real
            // fica para quando uma conta grande justificar.
            $this->release(900);
        }
    }

    public function failed(Throwable $e): void
    {
        MetaAdsSyncExecucao::query()
            ->where('meta_ads_conta_id', $this->conta->id)
            ->whereNull('concluido_em')
            ->latest('id')
            ->first()?->forceFill([
                'status' => MetaAdsSyncStatus::Erro,
                'erro' => $e->getMessage(),
                'concluido_em' => now(),
            ])->save();
    }
}
