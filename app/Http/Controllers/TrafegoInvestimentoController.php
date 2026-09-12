<?php

namespace App\Http\Controllers;

use App\Enums\MetaAdsNivel;
use App\Http\Requests\StoreMetaAdsContaRequest;
use App\Http\Requests\UpdateMetaAdsContaRequest;
use App\Jobs\SincronizarEstruturaAnunciosMetaJob;
use App\Jobs\SincronizarInsightsAnunciosMetaJob;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Support\Meta\MarketingApiClient;
use App\Support\Meta\MetricaInvestimento;
use App\Support\Meta\MetricasInvestimentoMeta;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aba `/trafego → Investimento`: painel de mídia paga (investimento × resultados
 * do CRM) + CRUD das contas de anúncio. Só leitura da Meta — pausar/ativar
 * conjunto é escopo futuro. O `access_token` nunca volta ao front.
 */
class TrafegoInvestimentoController extends Controller
{
    private const PERIODOS = [7, 30, 90];

    public function __construct(private readonly MetricasInvestimentoMeta $metricas) {}

    public function index(Request $request): Response
    {
        $periodo = (int) $request->integer('periodo', 30);
        $periodo = in_array($periodo, self::PERIODOS, true) ? $periodo : 30;

        $contas = MetaAdsConta::query()->ativas()->with('atualizadoPor:id,name')->orderBy('nome')->get();

        $nivel = MetaAdsNivel::tryFrom($request->string('nivel')->toString()) ?? MetaAdsNivel::Campanha;
        if ($nivel === MetaAdsNivel::Conta) {
            $nivel = MetaAdsNivel::Campanha;
        }

        $contaSelId = $request->integer('conta') ?: null;
        $campanhaDrill = $request->integer('campanha') ?: null;
        $conjuntoDrill = $request->integer('conjunto') ?: null;

        $filtroPaiIds = null;

        if ($conjuntoDrill !== null) {
            $paiConta = MetaAdsConjunto::query()->whereKey($conjuntoDrill)->value('meta_ads_conta_id');
            $contaSelId = is_int($paiConta) ? $paiConta : $contaSelId;
            $nivel = MetaAdsNivel::Anuncio;
            $filtroPaiIds = MetaAdsAnuncio::query()->where('meta_ads_conjunto_id', $conjuntoDrill)->pluck('id')->all();
        } elseif ($campanhaDrill !== null) {
            $paiConta = MetaAdsCampanha::query()->whereKey($campanhaDrill)->value('meta_ads_conta_id');
            $contaSelId = is_int($paiConta) ? $paiConta : $contaSelId;
            $nivel = MetaAdsNivel::Conjunto;
            $filtroPaiIds = MetaAdsConjunto::query()->where('meta_ads_campanha_id', $campanhaDrill)->pluck('id')->all();
        }

        /** @var Collection<int, MetaAdsConta> $alvo */
        $alvo = $contaSelId !== null ? $contas->where('id', $contaSelId)->values() : $contas;

        $de = CarbonImmutable::now()->subDays($periodo - 1)->startOfDay();
        $ate = CarbonImmutable::now()->startOfDay();

        $linhas = $alvo
            ->flatMap(fn (MetaAdsConta $c): Collection => $this->metricas->para($c, $de, $ate, $nivel))
            ->when(
                $filtroPaiIds !== null,
                fn (Collection $c): Collection => $c->filter(
                    fn (MetricaInvestimento $m): bool => in_array($m->objetoId, (array) $filtroPaiIds, true),
                ),
            )
            ->sortByDesc(fn (MetricaInvestimento $m): int => $m->investidoCentavos)
            ->map(fn (MetricaInvestimento $m): array => $m->toArray())
            ->values();

        return Inertia::render('crm/TrafegoInvestimento', [
            'contas' => $contas->map(fn (MetaAdsConta $c): array => $this->contaSegura($c))->values(),
            'kpis' => $this->kpis($alvo, $de, $ate),
            'linhas' => $linhas,
            'serie' => $this->serieAgregada($alvo, $de, $ate),
            'filtros' => [
                'periodo' => $periodo,
                'conta' => $contaSelId,
                'nivel' => $nivel->value,
                'campanha' => $campanhaDrill,
                'conjunto' => $conjuntoDrill,
            ],
            'opcoes' => [
                'periodos' => self::PERIODOS,
                'niveis' => [
                    ['value' => 'campanha', 'label' => 'Campanha'],
                    ['value' => 'conjunto', 'label' => 'Conjunto'],
                    ['value' => 'anuncio', 'label' => 'Anúncio'],
                ],
            ],
            'saude' => Inertia::defer(fn (): array => $contas
                ->mapWithKeys(fn (MetaAdsConta $c): array => [
                    $c->id => $this->statusAoVivo(app(MarketingApiClient::class), $c),
                ])
                ->all()),
        ]);
    }

    public function storeConta(StoreMetaAdsContaRequest $request, MarketingApiClient $client): RedirectResponse
    {
        $conta = MetaAdsConta::query()->create([
            ...$request->validated(),
            'atualizado_por_user_id' => $request->user()?->id,
        ]);

        $client->registrarVerificacao($conta);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Conta {$conta->nome} cadastrada.".$this->resumoVerificacao($conta->fresh()),
        ]);

        return redirect()->route('trafego.investimento.index');
    }

    public function updateConta(
        UpdateMetaAdsContaRequest $request,
        MetaAdsConta $conta,
        MarketingApiClient $client,
    ): RedirectResponse {
        $dados = $request->validated();
        $tokenNovo = filled($dados['access_token'] ?? null);

        if (! $tokenNovo) {
            unset($dados['access_token']);
        }

        $conta->update([...$dados, 'atualizado_por_user_id' => $request->user()?->id]);

        if ($tokenNovo) {
            $client->registrarVerificacao($conta->fresh());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Conta {$conta->nome} salva.",
        ]);

        return redirect()->route('trafego.investimento.index');
    }

    public function destroyConta(MetaAdsConta $conta): RedirectResponse
    {
        if ($conta->campanhas()->exists() || $conta->insightsDiarios()->exists()) {
            $conta->update(['ativo' => false]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Conta desativada (há histórico de sincronização).',
            ]);

            return redirect()->route('trafego.investimento.index');
        }

        $nome = $conta->nome;
        $conta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Conta {$nome} removida."]);

        return redirect()->route('trafego.investimento.index');
    }

    public function testarConexao(MetaAdsConta $conta, MarketingApiClient $client): RedirectResponse
    {
        $resultado = $client->registrarVerificacao($conta);
        $conta->refresh();

        Inertia::flash('toast', $resultado->ok
            ? ['type' => 'success', 'message' => 'Token OK.'.$this->resumoVerificacao($conta)]
            : ['type' => 'error', 'message' => $resultado->errorMessage ?? 'Não foi possível validar o token na Meta.']);

        return back();
    }

    public function sincronizar(): RedirectResponse
    {
        $contas = MetaAdsConta::query()->ativas()->get();
        $dias = (int) config('meta.ads.reprocessar_dias', 28);
        $de = CarbonImmutable::now()->subDays($dias - 1)->startOfDay();
        $ate = CarbonImmutable::now()->startOfDay();

        $contas->each(fn (MetaAdsConta $conta) => Bus::chain([
            new SincronizarEstruturaAnunciosMetaJob($conta),
            new SincronizarInsightsAnunciosMetaJob($conta, $de->toDateString(), $ate->toDateString()),
        ])->onQueue((string) config('meta.ads.queue'))->dispatch());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $contas->isEmpty()
                ? 'Nenhuma conta ativa para sincronizar.'
                : "Sincronização de {$contas->count()} conta(s) em andamento.",
        ]);

        return back();
    }

    // ---------------------------------------------------------------------

    /**
     * @param  Collection<int, MetaAdsConta>  $contas
     * @return array<int, array{label: string, value: string, secundario?: string}>
     */
    private function kpis(Collection $contas, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        /** @var Collection<int, MetricaInvestimento> $linhas */
        $linhas = $contas->flatMap(fn (MetaAdsConta $c): Collection => $this->metricas->para($c, $de, $ate, MetaAdsNivel::Campanha));

        $investido = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->investidoCentavos);
        $leadsMeta = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->leadsMeta);
        $leadsCrm = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->leadsCrm);
        $reunioes = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->reunioes);
        $contratos = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->contratos);
        $receita = (int) $linhas->sum(fn (MetricaInvestimento $m): int => $m->receitaCentavos);

        $entregando = MetaAdsAnuncio::query()
            ->vigentes()
            ->where('effective_status', 'ACTIVE')
            ->whereIn('meta_ads_conta_id', $contas->pluck('id')->all() ?: [0])
            ->count();

        $div = fn (int $n, int $d): int => (int) round($n / max($d, 1));

        return [
            ['label' => 'Investido', 'value' => $this->brl($investido)],
            ['label' => 'CPL (CRM)', 'value' => $this->brl($div($investido, $leadsCrm)), 'secundario' => 'Meta: '.$this->brl($div($investido, $leadsMeta))],
            ['label' => 'Custo por contrato', 'value' => $this->brl($div($investido, $contratos)), 'secundario' => "{$contratos} contrato(s) · {$reunioes} reunião(ões)"],
            ['label' => 'ROAS', 'value' => $investido > 0 ? number_format($receita / $investido, 2, ',', '.') : '0,00', 'secundario' => 'Receita: '.$this->brl($receita).' · '.$entregando.' anúncio(s) entregando'],
        ];
    }

    /**
     * @param  Collection<int, MetaAdsConta>  $contas
     * @return list<array{referencia: string, investido_centavos: int, leads_crm: int}>
     */
    private function serieAgregada(Collection $contas, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $acc = [];

        foreach ($contas as $conta) {
            foreach ($this->metricas->serieDiaria($conta, $de, $ate) as $ponto) {
                $ref = $ponto['referencia'];
                $acc[$ref] ??= ['referencia' => $ref, 'investido_centavos' => 0, 'leads_crm' => 0];
                $acc[$ref]['investido_centavos'] += $ponto['investido_centavos'];
                $acc[$ref]['leads_crm'] += $ponto['leads_crm'];
            }
        }

        ksort($acc);

        return array_values($acc);
    }

    /**
     * @return array<string, mixed>
     */
    private function contaSegura(MetaAdsConta $conta): array
    {
        return [
            'id' => $conta->id,
            'nome' => $conta->nome,
            'ad_account_id' => $conta->ad_account_id,
            'business_id' => $conta->business_id,
            'token_mascarado' => $conta->mascararToken(),
            'token_definido' => $conta->token_ultimos4 !== null,
            'token_valido' => $conta->token_valido,
            'token_scopes' => $conta->token_scopes ?? [],
            'escreve_habilitado' => $conta->temEscopo('ads_management'),
            'moeda' => $conta->moeda,
            'conta_status' => $conta->conta_status,
            'estrutura_sincronizada_em' => $conta->estrutura_sincronizada_em?->toIso8601String(),
            'insights_sincronizados_em' => $conta->insights_sincronizados_em?->toIso8601String(),
            'atualizado_por' => $conta->atualizadoPor?->name,
            'gerenciador_url' => 'https://business.facebook.com/adsmanager/manage/campaigns?act='.$conta->ad_account_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusAoVivo(MarketingApiClient $client, MetaAdsConta $conta): array
    {
        $resultado = $client->verificarConta($conta);
        $account = data_get($resultado->dados, '0.account', []);

        return [
            'ok' => $resultado->ok,
            'nome' => data_get($account, 'name'),
            'moeda' => data_get($account, 'currency'),
            'conta_status' => data_get($account, 'account_status'),
            'escopos' => data_get($resultado->dados, '0.scopes', []),
            'mensagem' => $resultado->errorMessage,
        ];
    }

    private function resumoVerificacao(?MetaAdsConta $conta): string
    {
        if ($conta === null) {
            return '';
        }

        if ($conta->token_valido !== true) {
            return ' O token não tem a permissão ads_read.';
        }

        $escopos = implode(', ', $conta->token_scopes ?? []);

        return ' '.trim(($conta->moeda ?? '').' · '.$escopos, ' ·');
    }

    private function brl(int $centavos): string
    {
        return 'R$ '.number_format($centavos / 100, 2, ',', '.');
    }
}
