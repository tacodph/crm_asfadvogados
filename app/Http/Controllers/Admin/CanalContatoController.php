<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCanalContatoRequest;
use App\Http\Requests\Admin\UpdateCanalContatoRequest;
use App\Models\CanalContato;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CanalContatoController extends Controller
{
    public function index(): Response
    {
        $canais = CanalContato::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'cor', 'ordem'])
            ->map(fn (CanalContato $item): array => [
                'id' => $item->id,
                'slug' => $item->slug,
                'nome' => $item->nome,
                'cor' => $item->cor,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();

        return Inertia::render('crm/admin/CanaisContato', [
            'canais' => $canais,
        ]);
    }

    public function store(StoreCanalContatoRequest $request): RedirectResponse
    {
        CanalContato::query()->create($request->validated());

        return redirect()->route('admin.canais-contato.index');
    }

    public function edit(CanalContato $canalContato): Response
    {
        return Inertia::render('crm/admin/CanalContatoEdit', [
            'canal' => [
                'id' => $canalContato->id,
                'slug' => $canalContato->slug,
                'nome' => $canalContato->nome,
                'cor' => $canalContato->cor,
                'ordem' => $canalContato->ordem,
            ],
        ]);
    }

    public function update(
        UpdateCanalContatoRequest $request,
        CanalContato $canalContato,
    ): RedirectResponse {
        $canalContato->update($request->validated());

        return redirect()->route('admin.canais-contato.index');
    }

    public function destroy(CanalContato $canalContato): RedirectResponse
    {
        if (
            $canalContato->contatos()->exists()
            || $canalContato->negociacoes()->exists()
        ) {
            return back()->withErrors([
                'canal' => 'Não é possível excluir: há contatos ou negociações vinculadas.',
            ]);
        }

        $canalContato->delete();

        return redirect()->route('admin.canais-contato.index');
    }
}
