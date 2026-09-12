<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEtapaFunilRequest;
use App\Http\Requests\Admin\UpdateEtapaFunilRequest;
use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EtapaFunilController extends Controller
{
    public function store(
        StoreEtapaFunilRequest $request,
        Funil $funil,
    ): RedirectResponse {
        $funil->etapas()->create($request->validated());

        return redirect()->route('admin.funis.edit', ['funil' => $funil]);
    }

    public function edit(Funil $funil, EtapaFunil $etapa): Response
    {
        abort_unless($etapa->funil_id === $funil->id, 404);

        $etapas = $funil->etapas()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get(['id', 'nome', 'ordem'])
            ->map(fn (EtapaFunil $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/EtapaFunilEdit', [
            'funil' => [
                'id' => $funil->id,
                'nome' => $funil->nome,
            ],
            'etapa' => [
                'id' => $etapa->id,
                'nome' => $etapa->nome,
                'sla' => $etapa->sla,
                'campos' => $etapa->campos,
                'meta_evento' => $etapa->meta_evento,
                'exige_motivo' => $etapa->exige_motivo,
                'ordem' => $etapa->ordem,
                'cor_fundo' => $etapa->cor_fundo,
                'cor_texto' => $etapa->cor_texto,
                'cor_suave' => $etapa->cor_suave,
            ],
            'etapas' => $etapas,
        ]);
    }

    public function update(
        UpdateEtapaFunilRequest $request,
        Funil $funil,
        EtapaFunil $etapa,
    ): RedirectResponse {
        abort_unless($etapa->funil_id === $funil->id, 404);

        $etapa->update($request->validated());

        return redirect()->route('admin.funis.edit', ['funil' => $funil]);
    }

    public function destroy(Funil $funil, EtapaFunil $etapa): RedirectResponse
    {
        abort_unless($etapa->funil_id === $funil->id, 404);

        if ($etapa->negociacoes()->exists()) {
            return back()->withErrors([
                'etapa' => 'Não é possível excluir: há negociações nesta etapa.',
            ]);
        }

        $etapa->delete();

        return redirect()->route('admin.funis.edit', ['funil' => $funil]);
    }
}
