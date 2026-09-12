<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTipoPessoaRequest;
use App\Http\Requests\Admin\UpdateTipoPessoaRequest;
use App\Models\TipoPessoa;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TipoPessoaController extends Controller
{
    public function index(): Response
    {
        $tipos = TipoPessoa::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'ordem'])
            ->map(fn (TipoPessoa $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/TiposPessoa', [
            'tipos' => $tipos,
        ]);
    }

    public function store(StoreTipoPessoaRequest $request): RedirectResponse
    {
        TipoPessoa::query()->create($request->validated());

        return redirect()->route('admin.tipos-pessoa.index');
    }

    public function edit(TipoPessoa $tipoPessoa): Response
    {
        return Inertia::render('crm/admin/TipoPessoaEdit', [
            'tipo' => [
                'id' => $tipoPessoa->id,
                'slug' => $tipoPessoa->slug,
                'nome' => $tipoPessoa->nome,
                'ordem' => $tipoPessoa->ordem,
            ],
        ]);
    }

    public function update(
        UpdateTipoPessoaRequest $request,
        TipoPessoa $tipoPessoa,
    ): RedirectResponse {
        $tipoPessoa->update($request->validated());

        return redirect()->route('admin.tipos-pessoa.index');
    }

    public function destroy(TipoPessoa $tipoPessoa): RedirectResponse
    {
        if ($tipoPessoa->contatos()->exists()) {
            return back()->withErrors([
                'tipo' => 'Não é possível excluir: há contatos vinculados.',
            ]);
        }

        $tipoPessoa->delete();

        return redirect()->route('admin.tipos-pessoa.index');
    }
}
