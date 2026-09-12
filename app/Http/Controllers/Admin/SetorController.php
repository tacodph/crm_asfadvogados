<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSetorRequest;
use App\Http\Requests\Admin\UpdateSetorRequest;
use App\Models\Setor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SetorController extends Controller
{
    public function index(): Response
    {
        $setores = Setor::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'ordem'])
            ->map(fn (Setor $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/Setores', [
            'setores' => $setores,
        ]);
    }

    public function store(StoreSetorRequest $request): RedirectResponse
    {
        Setor::query()->create($request->validated());

        return redirect()->route('admin.setores.index');
    }

    public function edit(Setor $setor): Response
    {
        return Inertia::render('crm/admin/SetorEdit', [
            'setor' => [
                'id' => $setor->id,
                'slug' => $setor->slug,
                'nome' => $setor->nome,
                'ordem' => $setor->ordem,
            ],
        ]);
    }

    public function update(
        UpdateSetorRequest $request,
        Setor $setor,
    ): RedirectResponse {
        $setor->update($request->validated());

        return redirect()->route('admin.setores.index');
    }

    public function destroy(Setor $setor): RedirectResponse
    {
        if ($setor->empresas()->exists()) {
            return back()->withErrors([
                'setor' => 'Não é possível excluir: há empresas vinculadas.',
            ]);
        }

        $setor->delete();

        return redirect()->route('admin.setores.index');
    }
}
