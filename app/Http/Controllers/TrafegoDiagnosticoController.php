<?php

namespace App\Http\Controllers;

use App\Actions\Meta\SincronizarEstatisticasPixelMeta;
use App\Enums\MetaConversaoEventoStatus;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use App\Models\MetaConversaoEvento;
use App\Support\Meta\ConversionsApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Diagnóstico do Pixel por campanha (`/trafego/diagnostico`): totais locais
 * confiáveis + proxy local de qualidade de correspondência (EMQ) + o que a
 * Graph API devolve ao vivo (deferred, best-effort, pode atrasar 24–72h).
 */
class TrafegoDiagnosticoController extends Controller
{
    public function index(): Response
    {
        $configs = MetaConversaoConfig::query()->ativas()->orderBy('nome_campanha')->get();
        $desde = now()->subDays(30);

        return Inertia::render('crm/TrafegoDiagnostico', [
            'campanhas' => $configs->map(fn (MetaConversaoConfig $config) => $this->diagnostico($config, $desde))->values(),
            'meta' => Inertia::defer(fn (): array => $configs
                ->mapWithKeys(fn (MetaConversaoConfig $config) => [
                    $config->slug => app(ConversionsApiClient::class)->lerEstatisticas($config),
                ])
                ->all()),
        ]);
    }

    public function sincronizar(SincronizarEstatisticasPixelMeta $sincronizar): RedirectResponse
    {
        $campanhas = MetaConversaoConfig::query()->ativas()->get();
        $campanhas->each(fn (MetaConversaoConfig $config) => $sincronizar($config));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $campanhas->isEmpty()
                ? 'Nenhuma campanha ativa para sincronizar.'
                : "{$campanhas->count()} campanha(s) sincronizada(s) com a Meta.",
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostico(MetaConversaoConfig $config, \DateTimeInterface $desde): array
    {
        $base = fn () => MetaConversaoEvento::query()
            ->where('meta_conversao_config_id', $config->id)
            ->where('created_at', '>=', $desde);

        $enviados = (clone $base())->where('status', MetaConversaoEventoStatus::Enviado->value);
        $totalEnviados = (clone $enviados)->count();

        /** @var Collection<int, array<string, mixed>> $payloads */
        $payloads = (clone $enviados)->pluck('request_payload');

        // ponytail: proxy EMQ carrega os payloads dos enviados (30d) e conta em PHP.
        // Volume de leads de escritório = dezenas/mês. Migrar pra JSON path no SQL se crescer.
        $pct = fn (callable $tem): int => $totalEnviados === 0
            ? 0
            : (int) round(100 * $payloads->filter($tem)->count() / $totalEnviados);

        $serie = $config->estatisticas()
            ->where('referencia', '>=', $desde->format('Y-m-d'))
            ->orderBy('referencia')
            ->get(['referencia', 'pixel_last_fired_at', 'eventos_servidor', 'eventos_navegador', 'eventos_deduplicados'])
            ->map(fn (MetaConversaoEstatistica $e) => [
                'referencia' => $e->referencia->toDateString(),
                'servidor' => $e->eventos_servidor,
                'navegador' => $e->eventos_navegador,
                'deduplicados' => $e->eventos_deduplicados,
            ]);

        return [
            'slug' => $config->slug,
            'nome' => $config->nome_campanha,
            'pixel_id' => $config->pixel_id,
            'link_events_manager' => "https://business.facebook.com/events_manager2/list/dataset/{$config->pixel_id}",
            'ultimo_evento_em' => $config->ultimo_evento_em?->toIso8601String(),
            'token_valido' => $config->token_valido,
            'ultima_sincronizacao' => $config->estatisticas()
                ->latest('created_at')->first()?->created_at?->toIso8601String(),
            'totais' => [
                'enviados' => $totalEnviados,
                'com_erro' => (clone $base())->where('status', MetaConversaoEventoStatus::Erro->value)->count(),
                'descartados' => (clone $base())->where('status', MetaConversaoEventoStatus::Descartado->value)->count(),
                'deduplicados_estimados' => (clone $enviados)
                    ->whereHas('negociacao', fn ($q) => $q->whereNotNull('meta_event_id'))
                    ->count(),
            ],
            'emq_proxy' => [
                'em' => $pct(fn ($p) => filled(data_get($p, 'user_data.em'))),
                'ph' => $pct(fn ($p) => filled(data_get($p, 'user_data.ph'))),
                'fbp_fbc' => $pct(fn ($p) => filled(data_get($p, 'user_data.fbp')) || filled(data_get($p, 'user_data.fbc'))),
                'ip_ua' => $pct(fn ($p) => filled(data_get($p, 'user_data.client_ip_address'))
                    && filled(data_get($p, 'user_data.client_user_agent'))),
                'base' => $totalEnviados,
            ],
            'erros_por_codigo' => (clone $base())
                ->where('status', MetaConversaoEventoStatus::Erro->value)
                ->selectRaw('coalesce(error_code, \'sem código\') as codigo, count(*) as total')
                ->groupBy('codigo')
                ->pluck('total', 'codigo'),
            'serie_30d' => $serie,
        ];
    }
}
