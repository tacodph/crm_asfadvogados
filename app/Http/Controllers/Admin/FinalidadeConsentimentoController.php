<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinalidadeConsentimentoRequest;
use App\Http\Requests\Admin\UpdateFinalidadeConsentimentoRequest;
use App\Models\FinalidadeConsentimento;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinalidadeConsentimentoController extends Controller
{
    public function index(): Response
    {
        $finalidades = FinalidadeConsentimento::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'ordem'])
            ->map(fn (FinalidadeConsentimento $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/FinalidadesConsentimento', [
            'finalidades' => $finalidades,
        ]);
    }

    public function store(StoreFinalidadeConsentimentoRequest $request): RedirectResponse
    {
        FinalidadeConsentimento::query()->create($request->validated());

        return redirect()->route('admin.finalidades-consentimento.index');
    }

    public function edit(FinalidadeConsentimento $finalidade): Response
    {
        return Inertia::render('crm/admin/FinalidadeConsentimentoEdit', [
            'finalidade' => [
                'id' => $finalidade->id,
                'slug' => $finalidade->slug,
                'nome' => $finalidade->nome,
                'ordem' => $finalidade->ordem,
            ],
        ]);
    }

    public function update(
        UpdateFinalidadeConsentimentoRequest $request,
        FinalidadeConsentimento $finalidade,
    ): RedirectResponse {
        $finalidade->update($request->validated());

        return redirect()->route('admin.finalidades-consentimento.index');
    }

    public function destroy(FinalidadeConsentimento $finalidade): RedirectResponse
    {
        if ($finalidade->consentimentos()->exists()) {
            return back()->withErrors([
                'finalidade' => 'Não é possível excluir: há consentimentos de contatos vinculados.',
            ]);
        }

        $finalidade->delete();

        return redirect()->route('admin.finalidades-consentimento.index');
    }
}
