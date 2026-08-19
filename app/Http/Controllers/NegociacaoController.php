<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveNegociacaoEtapaRequest;
use App\Http\Resources\NegociacaoResource;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use Illuminate\Http\RedirectResponse;
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
                'funil.etapas:id,funil_id,ordem',
                'etapaFunil:id,funil_id,nome,ordem',
                'empresa:id,nome',
                'contato.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'canalContato:id,nome,cor',
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
     * Move a negotiation to another stage within the same funnel.
     */
    public function updateEtapa(MoveNegociacaoEtapaRequest $request, Negociacao $negociacao): RedirectResponse
    {
        $etapaId = (int) $request->validated('etapa_funil_id');

        if ($negociacao->etapa_funil_id !== $etapaId) {
            $negociacao->update([
                'etapa_funil_id' => $etapaId,
                'etapa_desde' => now(),
            ]);
        }

        return back();
    }
}
