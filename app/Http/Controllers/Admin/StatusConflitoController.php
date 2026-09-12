<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStatusConflitoRequest;
use App\Http\Requests\Admin\UpdateStatusConflitoRequest;
use App\Models\StatusConflito;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatusConflitoController extends Controller
{
    public function index(): Response
    {
        $status = StatusConflito::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get([
                'id',
                'slug',
                'nome',
                'cor_fundo',
                'cor_texto',
                'cor_fundo_detalhe',
                'cor_borda_detalhe',
                'ordem',
            ])
            ->map(fn (StatusConflito $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'cor_fundo' => $item->cor_fundo,
                'cor_texto' => $item->cor_texto,
                'cor_fundo_detalhe' => $item->cor_fundo_detalhe,
                'cor_borda_detalhe' => $item->cor_borda_detalhe,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/StatusConflitos', [
            'status' => $status,
        ]);
    }

    public function store(StoreStatusConflitoRequest $request): RedirectResponse
    {
        StatusConflito::query()->create($request->validated());

        return redirect()->route('admin.status-conflitos.index');
    }

    public function edit(StatusConflito $statusConflito): Response
    {
        return Inertia::render('crm/admin/StatusConflitoEdit', [
            'status' => [
                'id' => $statusConflito->id,
                'slug' => $statusConflito->slug,
                'nome' => $statusConflito->nome,
                'cor_fundo' => $statusConflito->cor_fundo,
                'cor_texto' => $statusConflito->cor_texto,
                'cor_fundo_detalhe' => $statusConflito->cor_fundo_detalhe,
                'cor_borda_detalhe' => $statusConflito->cor_borda_detalhe,
                'ordem' => $statusConflito->ordem,
            ],
        ]);
    }

    public function update(
        UpdateStatusConflitoRequest $request,
        StatusConflito $statusConflito,
    ): RedirectResponse {
        $statusConflito->update($request->validated());

        return redirect()->route('admin.status-conflitos.index');
    }

    public function destroy(StatusConflito $statusConflito): RedirectResponse
    {
        if ($statusConflito->empresas()->exists()) {
            return back()->withErrors([
                'status' => 'Não é possível excluir: há empresas vinculadas.',
            ]);
        }

        $statusConflito->delete();

        return redirect()->route('admin.status-conflitos.index');
    }
}
