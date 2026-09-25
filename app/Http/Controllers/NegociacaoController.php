<?php

namespace App\Http\Controllers;

use App\Actions\Crm\DistribuirNegociacaoResponsavel;
use App\Actions\Crm\RegistrarHistoricoNegociacao;
use App\Actions\Crm\SyncConclusaoNegociacao;
use App\Actions\Crm\SyncProximaTarefaNegociacao;
use App\Enums\StatusTarefaNegociacao;
use App\Http\Requests\MoveNegociacaoEtapaRequest;
use App\Http\Requests\StoreNegociacaoRequest;
use App\Http\Requests\UpdateNegociacaoRequest;
use App\Http\Resources\NegociacaoResource;
use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NegociacaoController extends Controller
{
    /**
     * Display the negotiations board and list.
     */
    public function index(): Response
    {
        $funis = Funil::query()
            ->with('etapas')
            ->orderBy('ordem')
            ->get();

        $negociacoes = Negociacao::query()
            ->with([
                'funil.etapas:id,funil_id,ordem,resultado',
                'etapaFunil:id,funil_id,nome,ordem,resultado',
                'empresa:id,nome',
                'contato.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'contato.consentimentos:id,contato_id,finalidade_consentimento_id,status_consentimento_id,revogado_em',
                'contato.consentimentos.finalidade:id,slug',
                'contato.consentimentos.statusConsentimento:id,slug',
                'canalContato:id,nome,cor',
                'statusAtendimento:id,nome,cor_fundo,cor_texto',
                'statusQualificacao:id,nome,cor_fundo,cor_texto',
                'responsavel:id,name',
                'historicos',
            ])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('crm/Negociacoes', [
            'funis' => $funis->map(fn (Funil $funil): array => [
                'id' => $funil->id,
                'slug' => $funil->slug,
                'nome' => $funil->nome,
                'distribuicao' => $funil->distribuicao,
                'etapas' => $funil->etapas->map(fn (EtapaFunil $etapa): array => [
                    'id' => $etapa->id,
                    'nome' => $etapa->nome,
                    'sla' => $etapa->sla,
                    'obrigatorio' => implode(' · ', $etapa->campos ?? []),
                    'corBg' => $etapa->cor_fundo,
                    'corFg' => $etapa->cor_texto,
                    'corSuave' => $etapa->cor_suave,
                ])->values()->all(),
            ])->values()->all(),
            'negociacoes' => NegociacaoResource::collection($negociacoes)->resolve(),
        ]);
    }

    /**
     * Show the form to create a new negotiation.
     */
    public function create(Request $request): Response
    {
        $contatoId = $request->filled('contato_id') ? $request->integer('contato_id') : null;
        $empresaId = $request->filled('empresa_id') ? $request->integer('empresa_id') : null;

        $contato = $contatoId === null
            ? null
            : Contato::query()->with('empresa:id,nome')->find($contatoId);

        $empresa = $empresaId === null
            ? $contato?->empresa
            : Empresa::query()->find($empresaId);

        if ($contato === null && $empresa !== null) {
            $contato = Contato::query()
                ->where('empresa_id', $empresa->id)
                ->orderBy('nome')
                ->first();
        }

        $funis = Funil::query()
            ->with(['etapas' => fn ($query) => $query->orderBy('ordem')])
            ->orderBy('ordem')
            ->get(['id', 'nome']);

        $funilPadrao = $request->filled('funil_id')
            ? $funis->firstWhere('id', $request->integer('funil_id'))
            : null;
        $funilPadrao ??= $funis->first();
        $etapaPadrao = $funilPadrao?->etapas->first();

        $contatos = Contato::query()
            ->when(
                $empresa !== null,
                fn ($query) => $query->where('empresa_id', $empresa->id),
            )
            ->orderBy('nome')
            ->get(['id', 'nome', 'empresa_id', 'canal_contato_id'])
            ->map(fn (Contato $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
                'empresa_id' => $item->empresa_id,
                'canal_contato_id' => $item->canal_contato_id,
            ])
            ->values()
            ->all();

        $empresas = Empresa::query()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Empresa $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        $canais = CanalContato::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (CanalContato $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        $responsaveis = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $item): array => [
                'id' => $item->id,
                'nome' => $item->name,
            ])
            ->values()
            ->all();

        // Autofill de origem quando o link do anúncio abre /negociacoes/create?utm_campaign=...
        $origemUtm = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'meta_ad_id'] as $campo) {
            $origemUtm[$campo] = $request->filled($campo) ? $request->string($campo)->toString() : '';
        }

        return Inertia::render('crm/NegociacoesCreate', [
            'defaults' => [
                'funil_id' => $funilPadrao?->id,
                'etapa_funil_id' => $etapaPadrao?->id,
                'contato_id' => $contato?->id,
                'empresa_id' => $empresa?->id ?? $contato?->empresa_id,
                'canal_contato_id' => $contato?->canal_contato_id ?? ($canais[0]['id'] ?? null),
                'responsavel_user_id' => null,
                'assunto' => '',
                'valor' => '0',
                'previsao_fechamento' => '',
                'proxima_tarefa' => 'Primeiro contato — SLA da etapa',
                'proxima_tarefa_em' => now()->toDateString(),
                'proxima_tarefa_hora' => '09:00',
                ...$origemUtm,
            ],
            'opcoes' => [
                'funis' => $funis->map(fn (Funil $funil): array => [
                    'id' => $funil->id,
                    'nome' => $funil->nome,
                    'distribuicao' => $funil->distribuicao,
                    'etapas' => $funil->etapas->map(fn (EtapaFunil $etapa): array => [
                        'id' => $etapa->id,
                        'nome' => $etapa->nome,
                    ])->values()->all(),
                ])->values()->all(),
                'regrasDistribuicao' => Funil::REGRAS_DISTRIBUICAO,                'contatos' => $contatos,
                'empresas' => $empresas,
                'canais' => $canais,
                'responsaveis' => $responsaveis,
            ],
            'origem' => [
                'contato_id' => $contato?->id,
                'empresa_id' => $empresa?->id,
                'contato_nome' => $contato?->nome,
                'empresa_nome' => $empresa?->nome,
            ],
        ]);
    }

    /**
     * Persist a new negotiation.
     */
    public function store(
        StoreNegociacaoRequest $request,
        DistribuirNegociacaoResponsavel $distribuir,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
    ): RedirectResponse {
        $payload = $request->validated();

        $contato = Contato::query()->findOrFail($payload['contato_id']);
        $empresaId = $payload['empresa_id'] ?? $contato->empresa_id;
        $empresa = $empresaId === null
            ? null
            : Empresa::query()->with('statusConflito:id,slug', 'setor:id,slug')->find($empresaId);

        $responsavelUserId = $payload['responsavel_user_id'] ?? null;

        if ($responsavelUserId === null) {
            $funil = Funil::query()->findOrFail($payload['funil_id']);
            $responsavelUserId = $distribuir($funil, $empresa)->id;
        }

        $capturouPixel = filled($payload['meta_event_id'] ?? null) || filled($payload['meta_fbp'] ?? null);

        $contextoPixel = $capturouPixel ? [
            'meta_client_ip' => $request->ip(),
            'meta_client_user_agent' => $request->userAgent(),
            'meta_captado_em' => now(),
        ] : [];

        $origemUtm = array_filter([
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'utm_term' => $payload['utm_term'] ?? null,
            'fbclid' => $payload['fbclid'] ?? null,
            'meta_ad_id' => $payload['meta_ad_id'] ?? null,
        ], static fn ($v): bool => filled($v));

        $negociacao = Negociacao::query()->create([
            ...$payload,
            'empresa_id' => $empresaId,
            'responsavel_user_id' => $responsavelUserId,
            'etapa_desde' => now(),
            'proxima_tarefa' => null,
            'previsao_fechamento' => $payload['previsao_fechamento'] ?? null,
            'proxima_tarefa_em' => null,
            'proxima_tarefa_hora' => null,
            'origem_utm' => $origemUtm !== [] ? $origemUtm : null,
            ...$contextoPixel,
        ]);

        $descricaoTarefa = $payload['proxima_tarefa'] ?? null;
        $dataTarefa = $payload['proxima_tarefa_em'] ?? null;
        $tarefaInicial = null;

        if (filled($descricaoTarefa) && filled($dataTarefa)) {
            $tarefaInicial = TarefaNegociacao::query()->create([
                'negociacao_id' => $negociacao->id,
                'descricao' => $descricaoTarefa,
                'data' => $dataTarefa,
                'hora' => $payload['proxima_tarefa_hora'] ?? null,
                'status' => StatusTarefaNegociacao::Pendente,
                'criado_por_user_id' => $request->user()?->id,
            ]);

            $syncProximaTarefa($negociacao);
        }

        $registrarHistorico->criacao($negociacao, $request->user());

        if ($tarefaInicial !== null) {
            $registrarHistorico->tarefaCriada($negociacao, $tarefaInicial, $request->user());
        }

        return redirect()->route('negociacoes.index', [
            'negociacao' => $negociacao->id,
        ]);
    }

    /**
     * Show the form to edit an existing negotiation.
     */
    public function edit(Negociacao $negociacao): Response
    {
        $negociacao->load([
            'contato.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
            'empresa:id,nome',
            'canalContato:id,nome,cor',
            'responsavel:id,name',
            'etapaFunil:id,nome',
            'funil:id,nome,slug',
            'tarefas',
            'historicos',
        ]);

        $funis = Funil::query()
            ->with(['etapas' => fn ($query) => $query->orderBy('ordem')])
            ->orderBy('ordem')
            ->get(['id', 'nome', 'distribuicao']);

        $contatos = Contato::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'empresa_id', 'canal_contato_id'])
            ->map(fn (Contato $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
                'empresa_id' => $item->empresa_id,
                'canal_contato_id' => $item->canal_contato_id,
            ])
            ->values()
            ->all();

        $empresas = Empresa::query()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Empresa $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        $canais = CanalContato::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (CanalContato $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        $responsaveis = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $item): array => [
                'id' => $item->id,
                'nome' => $item->name,
            ])
            ->values()
            ->all();

        $statusAtendimentos = StatusAtendimento::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (StatusAtendimento $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        $statusQualificacoes = StatusQualificacao::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (StatusQualificacao $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/NegociacoesEdit', [
            'negociacao' => [
                'id' => $negociacao->id,
                'funil_id' => $negociacao->funil_id,
                'etapa_funil_id' => $negociacao->etapa_funil_id,
                'contato_id' => $negociacao->contato_id,
                'empresa_id' => $negociacao->empresa_id,
                'canal_contato_id' => $negociacao->canal_contato_id,
                'status_atendimento_id' => $negociacao->status_atendimento_id,
                'status_qualificacao_id' => $negociacao->status_qualificacao_id,
                'motivo_desqualificacao' => $negociacao->motivo_desqualificacao ?? '',
                'continuidade_atendimento' => $negociacao->continuidade_atendimento ?? '',
                'observacoes_complementares' => $negociacao->observacoes_complementares ?? '',
                'responsavel_user_id' => $negociacao->responsavel_user_id,
                'assunto' => $negociacao->assunto,
                'valor' => (string) $negociacao->valor,
                'previsao_fechamento' => $negociacao->previsao_fechamento?->format('Y-m-d') ?? '',
                'tarefas' => $negociacao->tarefas->map(fn (TarefaNegociacao $tarefa): array => [
                    'id' => $tarefa->id,
                    'descricao' => $tarefa->descricao,
                    'data' => $tarefa->data->format('Y-m-d'),
                    'hora' => $tarefa->hora?->format('H:i') ?? '',
                    'status' => $tarefa->status->value,
                    'statusLabel' => $tarefa->status->label(),
                    'concluidaEm' => $tarefa->concluida_em?->format('d/m/Y H:i'),
                ])->values()->all(),
                'historicos' => NegociacaoResource::historicosTimeline($negociacao->historicos),
            ],
            'resumo' => NegociacaoResource::resumoComercial($negociacao),
            'opcoes' => [
                'funis' => $funis->map(fn (Funil $funil): array => [
                    'id' => $funil->id,
                    'nome' => $funil->nome,
                    'distribuicao' => $funil->distribuicao,
                    'etapas' => $funil->etapas->map(fn (EtapaFunil $etapa): array => [
                        'id' => $etapa->id,
                        'nome' => $etapa->nome,
                    ])->values()->all(),
                ])->values()->all(),
                'regrasDistribuicao' => Funil::REGRAS_DISTRIBUICAO,
                'contatos' => $contatos,
                'empresas' => $empresas,
                'canais' => $canais,
                'responsaveis' => $responsaveis,
                'statusAtendimentos' => $statusAtendimentos,
                'statusQualificacoes' => $statusQualificacoes,
                'statusTarefa' => collect(StatusTarefaNegociacao::cases())
                    ->map(fn (StatusTarefaNegociacao $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Update an existing negotiation.
     */
    public function update(
        UpdateNegociacaoRequest $request,
        Negociacao $negociacao,
        DistribuirNegociacaoResponsavel $distribuir,
        RegistrarHistoricoNegociacao $registrarHistorico,
        SyncConclusaoNegociacao $syncConclusao,
    ): RedirectResponse {
        $payload = $request->validated();

        $contato = Contato::query()->findOrFail($payload['contato_id']);
        $empresaId = $payload['empresa_id'] ?? $contato->empresa_id;
        $empresa = $empresaId === null
            ? null
            : Empresa::query()->with('statusConflito:id,slug', 'setor:id,slug')->find($empresaId);

        $responsavelUserId = $payload['responsavel_user_id'] ?? null;

        if ($responsavelUserId === null) {
            $funil = Funil::query()->findOrFail($payload['funil_id']);
            $responsavelUserId = $distribuir($funil, $empresa)->id;
        }

        $antes = [
            'funil_id' => $negociacao->funil_id,
            'etapa_funil_id' => $negociacao->etapa_funil_id,
            'contato_id' => $negociacao->contato_id,
            'empresa_id' => $negociacao->empresa_id,
            'canal_contato_id' => $negociacao->canal_contato_id,
            'status_atendimento_id' => $negociacao->status_atendimento_id,
            'status_qualificacao_id' => $negociacao->status_qualificacao_id,
            'motivo_desqualificacao' => $negociacao->motivo_desqualificacao,
            'continuidade_atendimento' => $negociacao->continuidade_atendimento,
            'observacoes_complementares' => $negociacao->observacoes_complementares,
            'responsavel_user_id' => $negociacao->responsavel_user_id,
            'assunto' => $negociacao->assunto,
            'valor' => $negociacao->valor,
            'previsao_fechamento' => $negociacao->previsao_fechamento?->toDateString(),
        ];

        $etapaAlterada = $negociacao->etapa_funil_id !== (int) $payload['etapa_funil_id']
            || $negociacao->funil_id !== (int) $payload['funil_id'];

        $negociacao->update([
            ...$payload,
            'empresa_id' => $empresaId,
            'status_atendimento_id' => $payload['status_atendimento_id'] ?? null,
            'status_qualificacao_id' => $payload['status_qualificacao_id'] ?? null,
            'motivo_desqualificacao' => filled($payload['motivo_desqualificacao'] ?? null)
                ? $payload['motivo_desqualificacao']
                : null,
            'continuidade_atendimento' => filled($payload['continuidade_atendimento'] ?? null)
                ? $payload['continuidade_atendimento']
                : null,
            'observacoes_complementares' => filled($payload['observacoes_complementares'] ?? null)
                ? $payload['observacoes_complementares']
                : null,
            'responsavel_user_id' => $responsavelUserId,
            'previsao_fechamento' => $payload['previsao_fechamento'] ?? null,
            ...($etapaAlterada ? ['etapa_desde' => now()] : []),
        ]);

        if ($etapaAlterada) {
            $syncConclusao($negociacao->fresh(['funil.etapas', 'etapaFunil']));
        }

        $negociacao->unsetRelations();
        $registrarHistorico->atualizacao($negociacao->fresh(), $antes, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Negociação atualizada.',
        ]);

        return redirect()->route('negociacoes.edit', $negociacao);
    }

    /**
     * Cycle the active funnel distribution rule (prototype “Alterar regra”).
     */
    public function cycleDistribuicao(
        Funil $funil,
        DistribuirNegociacaoResponsavel $distribuir,
    ): RedirectResponse {
        $proxima = $funil->proximaRegraDistribuicao();

        $funil->update([
            'distribuicao' => $proxima,
            'ultimo_responsavel_user_id' => null,
        ]);

        $alteradas = $distribuir->redistribuirAbertas($funil->fresh());

        $mensagem = $alteradas === 0
            ? "Distribuição do funil alterada para “{$proxima}”."
            : "Distribuição alterada para “{$proxima}”. {$alteradas} negociação(ões) redistribuída(s).";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $mensagem,
        ]);

        return redirect()->route('negociacoes.index');
    }

    /**
     * Move a negotiation to another stage within the same funnel.
     */
    public function updateEtapa(
        MoveNegociacaoEtapaRequest $request,
        Negociacao $negociacao,
        RegistrarHistoricoNegociacao $registrarHistorico,
        SyncConclusaoNegociacao $syncConclusao,
    ): RedirectResponse {
        $etapaId = (int) $request->validated('etapa_funil_id');

        if ($negociacao->etapa_funil_id !== $etapaId) {
            $etapaAnterior = $negociacao->etapaFunil;
            $etapaNova = EtapaFunil::query()->findOrFail($etapaId);

            $negociacao->update([
                'etapa_funil_id' => $etapaId,
                'etapa_desde' => now(),
            ]);

            $syncConclusao($negociacao->fresh(['funil.etapas', 'etapaFunil']));

            $registrarHistorico->etapaAlterada(
                $negociacao,
                $etapaAnterior,
                $etapaNova,
                $request->user(),
            );
        }

        return back();
    }
}
