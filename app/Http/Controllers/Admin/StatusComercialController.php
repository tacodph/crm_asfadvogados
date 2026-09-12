<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStatusComercialRequest;
use App\Http\Requests\Admin\UpdateStatusComercialRequest;
use App\Models\StatusComercial;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatusComercialController extends Controller
{
    public function index(): Response
    {
        $status = StatusComercial::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get([
                'id',
                'slug',
                'nome',
                'descricao',
                'cor_fundo',
                'cor_texto',
                'ordem',
            ])
            ->map(fn (StatusComercial $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'descricao' => $item->descricao,
                'cor_fundo' => $item->cor_fundo,
                'cor_texto' => $item->cor_texto,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/StatusComerciais', [
            'status' => $status,
        ]);
    }

    public function store(StoreStatusComercialRequest $request): RedirectResponse
    {
        StatusComercial::query()->create($request->validated());

        return redirect()->route('admin.status-comerciais.index');
    }

    public function edit(StatusComercial $statusComercial): Response
    {
        return Inertia::render('crm/admin/StatusComercialEdit', [
            'status' => [
                'id' => $statusComercial->id,
                'slug' => $statusComercial->slug,
                'nome' => $statusComercial->nome,
                'descricao' => $statusComercial->descricao,
                'cor_fundo' => $statusComercial->cor_fundo,
                'cor_texto' => $statusComercial->cor_texto,
                'ordem' => $statusComercial->ordem,
            ],
        ]);
    }

    public function update(
        UpdateStatusComercialRequest $request,
        StatusComercial $statusComercial,
    ): RedirectResponse {
        $statusComercial->update($request->validated());

        return redirect()->route('admin.status-comerciais.index');
    }

    public function destroy(StatusComercial $statusComercial): RedirectResponse
    {
        if (
            $statusComercial->contatos()->exists()
            || $statusComercial->empresas()->exists()
        ) {
            return back()->withErrors([
                'status' => 'Não é possível excluir: há contatos ou empresas vinculados.',
            ]);
        }

        $statusComercial->delete();

        return redirect()->route('admin.status-comerciais.index');
    }
}
