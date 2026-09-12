<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStatusConsentimentoRequest;
use App\Http\Requests\Admin\UpdateStatusConsentimentoRequest;
use App\Models\StatusConsentimento;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatusConsentimentoController extends Controller
{
    public function index(): Response
    {
        $status = StatusConsentimento::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get([
                'id',
                'slug',
                'nome',
                'cor_fundo',
                'cor_texto',
                'visivel_cadastro',
                'ordem',
            ])
            ->map(fn (StatusConsentimento $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'cor_fundo' => $item->cor_fundo,
                'cor_texto' => $item->cor_texto,
                'visivel_cadastro' => $item->visivel_cadastro,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/StatusConsentimentos', [
            'status' => $status,
        ]);
    }

    public function store(StoreStatusConsentimentoRequest $request): RedirectResponse
    {
        StatusConsentimento::query()->create($request->validated());

        return redirect()->route('admin.status-consentimentos.index');
    }

    public function edit(StatusConsentimento $statusConsentimento): Response
    {
        return Inertia::render('crm/admin/StatusConsentimentoEdit', [
            'status' => [
                'id' => $statusConsentimento->id,
                'slug' => $statusConsentimento->slug,
                'nome' => $statusConsentimento->nome,
                'cor_fundo' => $statusConsentimento->cor_fundo,
                'cor_texto' => $statusConsentimento->cor_texto,
                'visivel_cadastro' => $statusConsentimento->visivel_cadastro,
                'ordem' => $statusConsentimento->ordem,
            ],
        ]);
    }

    public function update(
        UpdateStatusConsentimentoRequest $request,
        StatusConsentimento $statusConsentimento,
    ): RedirectResponse {
        $statusConsentimento->update($request->validated());

        return redirect()->route('admin.status-consentimentos.index');
    }

    public function destroy(StatusConsentimento $statusConsentimento): RedirectResponse
    {
        if (
            $statusConsentimento->contatos()->exists()
            || $statusConsentimento->consentimentos()->exists()
        ) {
            return back()->withErrors([
                'status' => 'Não é possível excluir: há contatos ou consentimentos vinculados.',
            ]);
        }

        $statusConsentimento->delete();

        return redirect()->route('admin.status-consentimentos.index');
    }
}
