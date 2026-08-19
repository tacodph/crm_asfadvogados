<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    /**
     * Display the companies directory.
     */
    public function index(): Response
    {
        $empresas = Empresa::query()
            ->with([
                'setor:id,nome',
                'uf:id,sigla',
                'responsavel:id,name',
                'statusConflito',
                'contatos.empresa:id,nome,cnpj',
                'contatos.tipoPessoa:id,slug,nome',
                'contatos.canalContato:id,slug,nome',
                'contatos.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
                'contatos.consentimentos.finalidade:id,slug,nome',
                'contatos.consentimentos.statusConsentimento:id,slug,nome,cor_texto',
                'contatos.negociacoes.etapaFunil:id,nome',
                'contatos.negociacoes.responsavel:id,name',
                'negociacoes.etapaFunil:id,nome',
                'negociacoes.responsavel:id,name',
            ])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/Empresas', [
            'empresas' => EmpresaResource::collection($empresas)->resolve(),
        ]);
    }
}
