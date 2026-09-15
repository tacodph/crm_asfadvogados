<?php

namespace App\Http\Controllers;

use App\Actions\Crm\ResolveLocalidadeFromMunicipio;
use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    /**
     * Display the companies directory.
     */
    public function index(): Response
    {
        $empresas = Empresa::query()
            ->with([
                'setor:id,nome',
                'uf:id,sigla',
                'responsavel:id,name',
                'statusConflito',
                'statusComercial:id,slug,nome,cor_fundo,cor_texto',
                'contatos.empresa:id,nome,cnpj',
                'contatos.tipoPessoa:id,slug,nome',
                'contatos.uf:id,sigla',
                'contatos.canalContato:id,slug,nome',
                'contatos.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'contatos.statusComercial:id,slug,nome,cor_fundo,cor_texto',
                'contatos.consentimentos.finalidade:id,slug,nome',
                'contatos.consentimentos.statusConsentimento:id,slug,nome,cor_texto',
                'contatos.negociacoes.etapaFunil:id,nome',
                'contatos.negociacoes.responsavel:id,name',
                'negociacoes.etapaFunil:id,nome',
                'negociacoes.responsavel:id,name',
            ])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/Empresas', [
            'empresas' => EmpresaResource::collection($empresas)->resolve(),
        ]);
    }

    /**
     * Show the form to create a new company.
     */
    public function create(): Response
    {
        $setores = Setor::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        $statusConflitos = StatusConflito::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        $statusComerciais = StatusComercial::query()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'descricao']);

        $statusComercialPadraoId = StatusComercial::query()
            ->where('slug', 'novo')
            ->value('id')
            ?? $statusComerciais->first()?->id;

        return Inertia::render('crm/EmpresasCreate', [
            'defaults' => [
                'setor_id' => $setores->first()?->id,
                'status_conflito_id' => $statusConflitos->first()?->id,
                'status_comercial_id' => $statusComercialPadraoId,
                'responsavel_user_id' => null,
            ],
            'opcoes' => [
                'setores' => $setores
                    ->map(fn (Setor $setor): array => [
                        'id' => $setor->id,
                        'nome' => $setor->nome,
                    ])
                    ->values()
                    ->all(),
                'statusConflitos' => $statusConflitos
                    ->map(fn (StatusConflito $status): array => [
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
                'responsaveis' => User::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'nome' => $user->name,
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
     * Persist a new company.
     */
    public function store(
        StoreEmpresaRequest $request,
        ResolveLocalidadeFromMunicipio $resolveLocalidade,
    ): RedirectResponse {
        $localidade = $resolveLocalidade($request->integer('municipio_id'));

        Empresa::query()->create([
            ...$request->safe()->except(['municipio_id']),
            ...$localidade,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empresa cadastrada.',
        ]);

        return redirect()->route('empresas.index');
    }

    /**
     * Show the company edit form.
     */
    public function edit(Empresa $empresa): Response
    {
        $empresa->load([
            'setor:id,nome',
            'uf:id,sigla',
            'municipio.estado:id,txt_sigla_uf,txt_uf',
            'statusConflito:id,nome',
            'responsavel:id,name',
            'contatos' => fn ($query) => $query
                ->orderBy('nome')
                ->select(['id', 'empresa_id', 'nome', 'cargo', 'email', 'telefone']),
        ]);

        $estadoId = $empresa->municipio?->estado_id
            ?? ($empresa->uf
                ? IbgeEstado::query()
                    ->whereRaw('UPPER(txt_sigla_uf) = ?', [strtoupper($empresa->uf->sigla)])
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

        return Inertia::render('crm/EmpresasEdit', [
            'empresa' => [
                'id' => $empresa->id,
                'nome' => $empresa->nome,
                'cnpj' => $empresa->cnpj,
                'setor_id' => $empresa->setor_id,
                'porte' => $empresa->porte,
                'estado_id' => $estadoId,
                'municipio_id' => $empresa->municipio_id,
                'status_conflito_id' => $empresa->status_conflito_id,
                'status_comercial_id' => $empresa->status_comercial_id,
                'conflito_texto' => $empresa->conflito_texto ?? '',
                'responsavel_user_id' => $empresa->responsavel_user_id,
            ],
            'contatos' => $empresa->contatos
                ->map(fn ($contato): array => [
                    'id' => $contato->id,
                    'nome' => $contato->nome,
                    'cargo' => $contato->cargo,
                    'email' => $contato->email,
                    'telefone' => $contato->telefone,
                ])
                ->values()
                ->all(),
            'opcoes' => [
                'setores' => Setor::query()
                    ->orderBy('ordem')
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (Setor $setor): array => [
                        'id' => $setor->id,
                        'nome' => $setor->nome,
                    ])
                    ->values()
                    ->all(),
                'statusConflitos' => StatusConflito::query()
                    ->orderBy('ordem')
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (StatusConflito $status): array => [
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
                'responsaveis' => User::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'nome' => $user->name,
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

    public function update(
        UpdateEmpresaRequest $request,
        Empresa $empresa,
        ResolveLocalidadeFromMunicipio $resolveLocalidade,
    ): RedirectResponse {
        $localidade = $resolveLocalidade($request->integer('municipio_id'));

        $empresa->update([
            ...$request->safe()->except(['municipio_id']),
            ...$localidade,
        ]);

        return redirect()->route('empresas.index');
    }
}
