<?php

namespace App\Http\Controllers;

use App\Actions\Crm\BuildAgendaDoDia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeuDiaController extends Controller
{
    /**
     * Página inicial do usuário: follow-ups, atrasos, reuniões e demais
     * tarefas do dia, mais as negociações abertas sem próximo passo.
     */
    public function index(Request $request, BuildAgendaDoDia $agenda): Response
    {
        $escopo = $request->query('escopo') === 'equipe' ? 'equipe' : 'minhas';

        if ($escopo === 'equipe') {
            Gate::authorize('ver-agenda-equipe');
        }

        return Inertia::render('crm/MeuDia', [
            'escopo' => $escopo,
            'podeVerEquipe' => Gate::allows('ver-agenda-equipe'),
            'agenda' => $agenda($escopo === 'equipe' ? null : $request->user(), now()),
        ]);
    }
}
