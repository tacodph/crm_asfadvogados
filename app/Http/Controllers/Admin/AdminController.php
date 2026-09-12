<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Administration hub for tenant domain catalogs.
     */
    public function index(): Response
    {
        return Inertia::render('crm/admin/Index', [
            'modulos' => [
                [
                    'key' => 'setores',
                    'titulo' => 'Setores',
                    'descricao' => 'Segmentos econômicos usados no cadastro de empresas.',
                ],
                [
                    'key' => 'canais',
                    'titulo' => 'Canais de contato',
                    'descricao' => 'Origens de leads para contatos e negociações.',
                ],
                [
                    'key' => 'status-conflito',
                    'titulo' => 'Status de conflito',
                    'descricao' => 'Estados de verificação de conflito de interesses.',
                ],
                [
                    'key' => 'status-comercial',
                    'titulo' => 'Status comercial',
                    'descricao' => 'Ciclo de vida compartilhado por contatos e empresas.',
                ],
                [
                    'key' => 'tipos-pessoa',
                    'titulo' => 'Tipos de pessoa',
                    'descricao' => 'Catálogo global PF/PJ (compartilhado entre escritórios).',
                ],
                [
                    'key' => 'finalidades',
                    'titulo' => 'Finalidades de consentimento',
                    'descricao' => 'Bases legais LGPD usadas no cadastro de contatos.',
                ],
                [
                    'key' => 'status',
                    'titulo' => 'Status de consentimento',
                    'descricao' => 'Estados de opt-in, pendência, revogação e suspensão.',
                ],
                [
                    'key' => 'funis',
                    'titulo' => 'Funis e etapas',
                    'descricao' => 'Pipelines de negociação e colunas do kanban.',
                ],
            ],
        ]);
    }
}
