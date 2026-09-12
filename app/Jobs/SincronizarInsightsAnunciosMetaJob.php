<?php

namespace App\Jobs;

use App\Actions\Meta\SincronizarInsightsAnunciosMeta;
use App\Enums\MetaAdsSyncStatus;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsSyncExecucao;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Sincroniza os insights diários de uma janela. Em rodada `parcial` (rate
 * limit), se a janela tem mais de 1 dia ela é partida ao meio e re-agendada
 * em dois jobs (evita reprocessar a janela inteira numa conta grande);
 * janela de 1 dia só recua.
 */
class SincronizarInsightsAnunciosMetaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [300, 900, 3600];

    public function __construct(
        public MetaAdsConta $conta,
        public string $de,
        public string $ate,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('meta-ads-insights-'.$this->conta->id)];
    }

    public function handle(SincronizarInsightsAnunciosMeta $sincronizar): void
    {
        $de = CarbonImmutable::parse($this->de)->startOfDay();
        $ate = CarbonImmutable::parse($this->ate)->startOfDay();

        $execucao = $sincronizar($this->conta, $de, $ate);

        if ($execucao->status !== MetaAdsSyncStatus::Parcial) {
            return;
        }

        $dias = (int) $de->diffInDays($ate);

        if ($dias < 1) {
            $this->release(900);

            return;
        }

        $meio = $de->addDays(intdiv($dias, 2));
        $fila = (string) config('meta.ads.queue');

        self::dispatch($this->conta, $de->toDateString(), $meio->toDateString())->onQueue($fila);
        self::dispatch($this->conta, $meio->addDay()->toDateString(), $ate->toDateString())->onQueue($fila);
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
