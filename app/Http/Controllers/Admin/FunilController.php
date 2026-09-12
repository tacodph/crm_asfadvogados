<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFunilRequest;
use App\Http\Requests\Admin\UpdateFunilRequest;
use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FunilController extends Controller
{
    public function index(): Response
    {
        $funis = Funil::query()
            ->withCount('etapas')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'distribuicao', 'ordem'])
            ->map(fn (Funil $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'distribuicao' => $item->distribuicao,
                'ordem' => $item->ordem,
                'etapas_count' => $item->etapas_count,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/Funis', [
            'funis' => $funis,
        ]);
    }

    public function store(StoreFunilRequest $request): RedirectResponse
    {
        Funil::query()->create($request->validated());

        return redirect()->route('admin.funis.index');
    }

    public function edit(Funil $funil): Response
    {
        $etapas = $funil->etapas()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get([
                'id',
                'nome',
                'sla',
                'campos',
                'exige_motivo',
                'ordem',
                'cor_fundo',
                'cor_texto',
                'cor_suave',
            ])
            ->map(fn (EtapaFunil $etapa): array => [
                'id' => $etapa->id,
                'nome' => $etapa->nome,
                'sla' => $etapa->sla,
                'campos' => $etapa->campos,
                'exige_motivo' => $etapa->exige_motivo,
                'ordem' => $etapa->ordem,
                'cor_fundo' => $etapa->cor_fundo,
                'cor_texto' => $etapa->cor_texto,
                'cor_suave' => $etapa->cor_suave,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/FunilEdit', [
            'funil' => [
                'id' => $funil->id,
                'slug' => $funil->slug,
                'nome' => $funil->nome,
                'distribuicao' => $funil->distribuicao,
                'ordem' => $funil->ordem,
            ],
            'etapas' => $etapas,
        ]);
    }

    public function update(
        UpdateFunilRequest $request,
        Funil $funil,
    ): RedirectResponse {
        $funil->update($request->validated());

        return redirect()->route('admin.funis.edit', ['funil' => $funil]);
    }

    public function destroy(Funil $funil): RedirectResponse
    {
        if ($funil->negociacoes()->exists()) {
            return back()->withErrors([
                'funil' => 'Não é possível excluir: há negociações vinculadas.',
            ]);
        }

        $funil->etapas()->delete();
        $funil->delete();

        return redirect()->route('admin.funis.index');
    }
}
