<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContatoResource;
use App\Models\Contato;
use Inertia\Inertia;
use Inertia\Response;

class ContatoController extends Controller
{
    /**
     * Display the contacts directory.
     */
    public function index(): Response
    {
        $contatos = Contato::query()
            ->with([
                'empresa:id,nome,cnpj',
                'tipoPessoa:id,slug,nome',
                'canalContato:id,slug,nome',
                'statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'consentimentos.finalidade:id,slug,nome',
                'consentimentos.statusConsentimento:id,slug,nome,cor_texto',
                'negociacoes.etapaFunil:id,nome',
                'negociacoes.responsavel:id,name',
            ])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/Contatos', [
            'contatos' => ContatoResource::collection($contatos)->resolve(),
        ]);
    }
}
