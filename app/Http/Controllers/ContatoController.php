<?php

namespace App\Http\Controllers;

use App\Actions\Crm\FindContatoDuplicatas;
use App\Actions\Crm\ResolveLocalidadeFromMunicipio;
use App\Actions\Crm\SyncContatoConsentimentos;
use App\Http\Requests\StoreContatoRequest;
use App\Http\Requests\UpdateContatoRequest;
use App\Http\Resources\ContatoResource;
use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\FinalidadeConsentimento;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\Negociacao;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContatoController extends Controller
{
    /**
     * Display the contacts directory.
     */
    public function index(): Response
    {
        $contatos = Contato::query()
            ->with([
                'empresa:id,nome,cnpj',
                'tipoPessoa:id,slug,nome',
                'uf:id,sigla',
                'canalContato:id,slug,nome',
                'statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'statusComercial:id,slug,nome,cor_fundo,cor_texto',
                'consentimentos.finalidade:id,slug,nome',
                'consentimentos.statusConsentimento:id,slug,nome,cor_texto',
                'negociacoes.etapaFunil:id,nome',
                'negociacoes.responsavel:id,name',
            ])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/Contatos', [
            'contatos' => ContatoResource::collection($contatos)->resolve(),
        ]);
    }

    /**
     * Show the form to create a new contact.
     */
    public function create(): Response
    {
        $statusPadraoId = StatusConsentimento::query()
            ->where('slug', 'nao-concedido')
            ->value('id')
            ?? StatusConsentimento::query()->orderBy('ordem')->value('id');

        $statusComercialPadraoId = StatusComercial::query()
            ->where('slug', 'novo')
            ->value('id')
            ?? StatusComercial::query()->orderBy('ordem')->value('id');

        $consentimentos = FinalidadeConsentimento::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome'])
            ->map(fn (FinalidadeConsentimento $finalidade): array => [
                'finalidade_consentimento_id' => $finalidade->id,
                'finalidade_nome' => $finalidade->nome,
                'finalidade_slug' => $finalidade->slug,
                'status_consentimento_id' => $statusPadraoId,
                'concedido_em' => '',
                'revogado_em' => '',
            ])
            ->values()
            ->all();

        $tiposPessoa = TipoPessoa::query()->orderBy('ordem')->get(['id', 'slug', 'nome']);
        $canais = CanalContato::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        $statusConsentimentos = StatusConsentimento::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        $statusComerciais = StatusComercial::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'descricao']);

        return Inertia::render('crm/ContatosCreate', [
            'defaults' => [
                'tipo_pessoa_id' => $tiposPessoa->first()?->id,
                'canal_contato_id' => $canais->first()?->id,
                'status_consentimento_id' => $statusPadraoId ?? $statusConsentimentos->first()?->id,
                'status_comercial_id' => $statusComercialPadraoId ?? $statusComerciais->first()?->id,
                'consentimentos' => $consentimentos,
            ],
            'opcoes' => [
                'tiposPessoa' => $tiposPessoa
                    ->map(fn (TipoPessoa $tipo): array => [
                        'id' => $tipo->id,
                        'slug' => $tipo->slug,
                        'nome' => $tipo->nome,
                    ])
                    ->values()
                    ->all(),
                'empresas' => Empresa::query()
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (Empresa $empresa): array => [
                        'id' => $empresa->id,
                        'nome' => $empresa->nome,
                    ])
                    ->values()
                    ->all(),
                'canais' => $canais
                    ->map(fn (CanalContato $canal): array => [
                        'id' => $canal->id,
                        'nome' => $canal->nome,
                    ])
                    ->values()
                    ->all(),
                'statusConsentimentos' => $statusConsentimentos
                    ->map(fn (StatusConsentimento $status): array => [
                        'id' => $status->id,
                        'nome' => $status->nome,
                    ])
                    ->values()
                    ->all(),
                'statusComerciais' => $statusComerciais
                    ->map(fn (StatusComercial $status): array => [
                        'id' => $status->id,
                        'nome' => $status->nome,
                        'descricao' => $status->descricao,
                    ])
                    ->values()
                    ->all(),
                'estados' => IbgeEstado::query()
                    ->whereNotNull('txt_sigla_uf')
                    ->orderBy('txt_sigla_uf')
                    ->get(['id', 'txt_sigla_uf', 'txt_uf'])
                    ->map(fn (IbgeEstado $estado): array => [
                        'id' => $estado->id,
                        'sigla' => strtoupper((string) $estado->txt_sigla_uf),
                        'nome' => $estado->txt_uf,
                    ])
                    ->values()
                    ->all(),
                'municipios' => [],
            ],
        ]);
    }

    /**
     * Preview deterministic duplicates for create/edit forms.
     */
    public function duplicatas(Request $request, FindContatoDuplicatas $find): JsonResponse
    {
        $matches = $find(
            email: $request->filled('email') ? $request->string('email')->toString() : null,
            telefone: $request->filled('telefone') ? $request->string('telefone')->toString() : null,
            cpf: $request->filled('cpf') ? $request->string('cpf')->toString() : null,
            ignoreId: $request->filled('ignore') ? $request->integer('ignore') : null,
        );

        return response()->json([
            'duplicatas' => $matches->values()->all(),
        ]);
    }

    /**
     * Persist a new contact and its per-finalidade consents.
     */
    public function store(
        StoreContatoRequest $request,
        SyncContatoConsentimentos $sync,
        ResolveLocalidadeFromMunicipio $resolveLocalidade,
    ): RedirectResponse {
        $payload = $request->payload();
        $municipioId = $payload['contato']['municipio_id'] ?? null;

        $localidade = $municipioId === null
            ? ['cidade' => null, 'uf_id' => null, 'municipio_id' => null]
            : $resolveLocalidade((int) $municipioId);

        $sync(
            new Contato,
            [
                ...collect($payload['contato'])->except('municipio_id')->all(),
                ...$localidade,
            ],
            $payload['consentimentos'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Contato cadastrado.',
        ]);

        return redirect()->route('contatos.index');
    }

    /**
     * Show the contact edit form.
     */
    public function edit(Contato $contato): Response
    {
        $contato->load([
            'empresa:id,nome',
            'tipoPessoa:id,slug,nome',
            'uf:id,sigla',
            'municipio.estado:id,txt_sigla_uf,txt_uf',
            'canalContato:id,nome',
            'statusConsentimento:id,nome',
            'consentimentos',
            'negociacoes:id,contato_id,assunto',
        ]);

        $consentimentosPorFinalidade = $contato->consentimentos
            ->keyBy('finalidade_consentimento_id');

        $statusPadraoId = StatusConsentimento::query()
            ->where('slug', 'nao-concedido')
            ->value('id')
            ?? $contato->status_consentimento_id
            ?? StatusConsentimento::query()->orderBy('ordem')->value('id');

        $consentimentos = FinalidadeConsentimento::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome'])
            ->map(function (FinalidadeConsentimento $finalidade) use ($consentimentosPorFinalidade, $statusPadraoId): array {
                /** @var ConsentimentoContato|null $existente */
                $existente = $consentimentosPorFinalidade->get($finalidade->id);

                return [
                    'finalidade_consentimento_id' => $finalidade->id,
                    'finalidade_nome' => $finalidade->nome,
                    'finalidade_slug' => $finalidade->slug,
                    'status_consentimento_id' => $existente?->status_consentimento_id ?? $statusPadraoId,
                    'concedido_em' => $existente?->concedido_em?->format('Y-m-d') ?? '',
                    'revogado_em' => $existente?->revogado_em?->format('Y-m-d') ?? '',
                ];
            })
            ->values()
            ->all();

        $estadoId = $contato->municipio?->estado_id
            ?? ($contato->uf
                ? IbgeEstado::query()
                    ->whereRaw('UPPER(txt_sigla_uf) = ?', [strtoupper($contato->uf->sigla)])
                    ->value('id')
                : null);

        $municipios = $estadoId === null
            ? []
            : IbgeMunicipio::query()
                ->where('estado_id', $estadoId)
                ->whereNotNull('txt_nome_municipios')
                ->orderBy('txt_nome_municipios')
                ->get(['id', 'txt_nome_municipios'])
                ->map(fn (IbgeMunicipio $municipio): array => [
                    'id' => $municipio->id,
                    'nome' => $municipio->txt_nome_municipios,
                ])
                ->values()
                ->all();

        return Inertia::render('crm/ContatosEdit', [
            'contato' => [
                'id' => $contato->id,
                'nome' => $contato->nome,
                'cargo' => $contato->cargo ?? '',
                'email' => $contato->email ?? '',
                'telefone' => $contato->telefone ?? '',
                'cpf' => $contato->cpf ?? '',
                'cep' => $contato->cep ?? '',
                'tipo_pessoa_id' => $contato->tipo_pessoa_id,
                'empresa_id' => $contato->empresa_id,
                'estado_id' => $estadoId,
                'municipio_id' => $contato->municipio_id,
                'canal_contato_id' => $contato->canal_contato_id,
                'status_consentimento_id' => $contato->status_consentimento_id,
                'status_comercial_id' => $contato->status_comercial_id,
                'registro_mesclado' => $contato->registro_mesclado,
                'observacao_deduplicacao' => $contato->observacao_deduplicacao ?? '',
                'consentimentos' => $consentimentos,
                'negociacoes' => $contato->negociacoes
                    ->map(fn (Negociacao $negociacao): array => [
                        'id' => $negociacao->id,
                        'titulo' => $negociacao->assunto,
                    ])
                    ->values()
                    ->all(),
            ],
            'opcoes' => [
                'tiposPessoa' => TipoPessoa::query()
                    ->orderBy('ordem')
                    ->get(['id', 'slug', 'nome'])
                    ->map(fn (TipoPessoa $tipo): array => [
                        'id' => $tipo->id,
                        'slug' => $tipo->slug,
                        'nome' => $tipo->nome,
                    ])
                    ->values()
                    ->all(),
                'empresas' => Empresa::query()
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (Empresa $empresa): array => [
                        'id' => $empresa->id,
                        'nome' => $empresa->nome,
                    ])
                    ->values()
                    ->all(),
                'canais' => CanalContato::query()
                    ->orderBy('ordem')
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (CanalContato $canal): array => [
                        'id' => $canal->id,
                        'nome' => $canal->nome,
                    ])
                    ->values()
                    ->all(),
                'statusConsentimentos' => StatusConsentimento::query()
                    ->orderBy('ordem')
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (StatusConsentimento $status): array => [
                        'id' => $status->id,
                        'nome' => $status->nome,
                    ])
                    ->values()
                    ->all(),
                'statusComerciais' => StatusComercial::query()
                    ->orderBy('ordem')
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'descricao'])
                    ->map(fn (StatusComercial $status): array => [
                        'id' => $status->id,
                        'nome' => $status->nome,
                        'descricao' => $status->descricao,
                    ])
                    ->values()
                    ->all(),
                'estados' => IbgeEstado::query()
                    ->whereNotNull('txt_sigla_uf')
                    ->orderBy('txt_sigla_uf')
                    ->get(['id', 'txt_sigla_uf', 'txt_uf'])
                    ->map(fn (IbgeEstado $estado): array => [
                        'id' => $estado->id,
                        'sigla' => strtoupper((string) $estado->txt_sigla_uf),
                        'nome' => $estado->txt_uf,
                    ])
                    ->values()
                    ->all(),
                'municipios' => $municipios,
            ],
        ]);
    }

    /**
     * Update the given contact and its per-finalidade consents.
     */
    public function update(
        UpdateContatoRequest $request,
        Contato $contato,
        SyncContatoConsentimentos $sync,
        ResolveLocalidadeFromMunicipio $resolveLocalidade,
    ): RedirectResponse {
        $payload = $request->payload();
        $municipioId = $payload['contato']['municipio_id'] ?? null;

        $localidade = $municipioId === null
            ? ['cidade' => null, 'uf_id' => null, 'municipio_id' => null]
            : $resolveLocalidade((int) $municipioId);

        $sync(
            $contato,
            [
                ...collect($payload['contato'])->except('municipio_id')->all(),
                ...$localidade,
            ],
            $payload['consentimentos'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Contato atualizado.',
        ]);

        return redirect()->route('contatos.edit', $contato);
    }
}
