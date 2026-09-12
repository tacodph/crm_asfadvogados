<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CanalContatoController;
use App\Http\Controllers\Admin\EtapaFunilController;
use App\Http\Controllers\Admin\FinalidadeConsentimentoController;
use App\Http\Controllers\Admin\FunilController;
use App\Http\Controllers\Admin\SetorController;
use App\Http\Controllers\Admin\StatusComercialController;
use App\Http\Controllers\Admin\StatusConflitoController;
use App\Http\Controllers\Admin\StatusConsentimentoController;
use App\Http\Controllers\Admin\TipoPessoaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ContatoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\IbgeMunicipioController;
use App\Http\Controllers\LandingExportController;
use App\Http\Controllers\NegociacaoController;
use App\Http\Controllers\NegociacaoTarefaController;
use App\Http\Controllers\PropostaController;
use App\Http\Controllers\TrafegoController;
use App\Http\Controllers\TrafegoDiagnosticoController;
use App\Http\Controllers\TrafegoEventoController;
use App\Http\Controllers\TrafegoInvestimentoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

require __DIR__.'/onboarding.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::inertia('atividades', 'crm/Atividades')->name('atividades.index');
    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::get('propostas', [PropostaController::class, 'index'])->name('propostas.index');
    Route::get('propostas/{proposta}', [PropostaController::class, 'show'])->name('propostas.show');
    Route::inertia('automacoes', 'crm/Automacoes')->name('automacoes.index');
    Route::get('trafego', [TrafegoController::class, 'index'])->name('trafego.index');
    Route::post('trafego', [TrafegoController::class, 'store'])->name('trafego.store');
    Route::patch('trafego/{config}', [TrafegoController::class, 'update'])->name('trafego.update');
    Route::delete('trafego/{config}', [TrafegoController::class, 'destroy'])->name('trafego.destroy');
    Route::post('trafego/{config}/testar-conexao', [TrafegoController::class, 'testarConexao'])
        ->name('trafego.testar-conexao');
    Route::post('trafego/{config}/evento-teste', [TrafegoController::class, 'eventoTeste'])
        ->name('trafego.evento-teste');
    Route::post('trafego-token-lead', [TrafegoController::class, 'gerarTokenLead'])
        ->name('trafego.token-lead');

    Route::get('trafego/eventos', [TrafegoEventoController::class, 'index'])->name('trafego.eventos.index');
    Route::get('trafego/eventos/{evento}', [TrafegoEventoController::class, 'show'])->name('trafego.eventos.show');
    Route::post('trafego/eventos/{evento}/reenviar', [TrafegoEventoController::class, 'reenviar'])
        ->name('trafego.eventos.reenviar');
    Route::get('trafego/diagnostico', [TrafegoDiagnosticoController::class, 'index'])
        ->name('trafego.diagnostico.index');
    Route::post('trafego/diagnostico/sincronizar', [TrafegoDiagnosticoController::class, 'sincronizar'])
        ->name('trafego.diagnostico.sincronizar');

    Route::get('trafego/investimento', [TrafegoInvestimentoController::class, 'index'])
        ->name('trafego.investimento.index');
    Route::post('trafego/investimento/contas', [TrafegoInvestimentoController::class, 'storeConta'])
        ->name('trafego.investimento.contas.store');
    Route::patch('trafego/investimento/contas/{conta}', [TrafegoInvestimentoController::class, 'updateConta'])
        ->name('trafego.investimento.contas.update');
    Route::delete('trafego/investimento/contas/{conta}', [TrafegoInvestimentoController::class, 'destroyConta'])
        ->name('trafego.investimento.contas.destroy');
    Route::post('trafego/investimento/contas/{conta}/testar-conexao', [TrafegoInvestimentoController::class, 'testarConexao'])
        ->name('trafego.investimento.contas.testar-conexao');
    Route::post('trafego/investimento/sincronizar', [TrafegoInvestimentoController::class, 'sincronizar'])
        ->name('trafego.investimento.sincronizar');
    Route::inertia('site', 'crm/Site')->name('site.index');
    Route::get('landing-export', [LandingExportController::class, 'index'])->name('landing-export.index');
    Route::get('landing-export/download', [LandingExportController::class, 'download'])->name('landing-export.download');
    Route::inertia('compliance', 'crm/Compliance')->name('compliance.index');
    Route::get('contatos', [ContatoController::class, 'index'])->name('contatos.index');
    Route::get('contatos/create', [ContatoController::class, 'create'])->name('contatos.create');
    Route::get('contatos/duplicatas', [ContatoController::class, 'duplicatas'])->name('contatos.duplicatas');
    Route::post('contatos', [ContatoController::class, 'store'])->name('contatos.store');
    Route::get('contatos/{contato}/edit', [ContatoController::class, 'edit'])->name('contatos.edit');
    Route::patch('contatos/{contato}', [ContatoController::class, 'update'])->name('contatos.update');
    Route::get('empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::get('empresas/create', [EmpresaController::class, 'create'])->name('empresas.create');
    Route::post('empresas', [EmpresaController::class, 'store'])->name('empresas.store');
    Route::get('empresas/{empresa}/edit', [EmpresaController::class, 'edit'])->name('empresas.edit');
    Route::patch('empresas/{empresa}', [EmpresaController::class, 'update'])->name('empresas.update');
    Route::get('ibge/municipios', [IbgeMunicipioController::class, 'index'])->name('ibge.municipios.index');
    Route::get('negociacoes', [NegociacaoController::class, 'index'])->name('negociacoes.index');
    Route::get('negociacoes/create', [NegociacaoController::class, 'create'])->name('negociacoes.create');
    Route::post('negociacoes', [NegociacaoController::class, 'store'])->name('negociacoes.store');
    Route::get('negociacoes/{negociacao}/edit', [NegociacaoController::class, 'edit'])->name('negociacoes.edit');
    Route::patch('negociacoes/{negociacao}', [NegociacaoController::class, 'update'])->name('negociacoes.update');
    Route::patch('negociacoes/funis/{funil}/distribuicao', [NegociacaoController::class, 'cycleDistribuicao'])
        ->name('negociacoes.distribuicao.cycle');
    Route::patch('negociacoes/{negociacao}/etapa', [NegociacaoController::class, 'updateEtapa'])->name('negociacoes.update-etapa');
    Route::post('negociacoes/{negociacao}/tarefas', [NegociacaoTarefaController::class, 'store'])
        ->name('negociacoes.tarefas.store');
    Route::patch('negociacoes/{negociacao}/tarefas/{tarefa}', [NegociacaoTarefaController::class, 'update'])
        ->name('negociacoes.tarefas.update')
        ->scopeBindings();
    Route::delete('negociacoes/{negociacao}/tarefas/{tarefa}', [NegociacaoTarefaController::class, 'destroy'])
        ->name('negociacoes.tarefas.destroy')
        ->scopeBindings();

    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');

    Route::get('admin/finalidades-consentimento', [FinalidadeConsentimentoController::class, 'index'])
        ->name('admin.finalidades-consentimento.index');
    Route::post('admin/finalidades-consentimento', [FinalidadeConsentimentoController::class, 'store'])
        ->name('admin.finalidades-consentimento.store');
    Route::get('admin/finalidades-consentimento/{finalidade}/edit', [FinalidadeConsentimentoController::class, 'edit'])
        ->name('admin.finalidades-consentimento.edit');
    Route::patch('admin/finalidades-consentimento/{finalidade}', [FinalidadeConsentimentoController::class, 'update'])
        ->name('admin.finalidades-consentimento.update');
    Route::delete('admin/finalidades-consentimento/{finalidade}', [FinalidadeConsentimentoController::class, 'destroy'])
        ->name('admin.finalidades-consentimento.destroy');

    Route::get('admin/status-consentimentos', [StatusConsentimentoController::class, 'index'])
        ->name('admin.status-consentimentos.index');
    Route::post('admin/status-consentimentos', [StatusConsentimentoController::class, 'store'])
        ->name('admin.status-consentimentos.store');
    Route::get('admin/status-consentimentos/{status_consentimento}/edit', [StatusConsentimentoController::class, 'edit'])
        ->name('admin.status-consentimentos.edit');
    Route::patch('admin/status-consentimentos/{status_consentimento}', [StatusConsentimentoController::class, 'update'])
        ->name('admin.status-consentimentos.update');
    Route::delete('admin/status-consentimentos/{status_consentimento}', [StatusConsentimentoController::class, 'destroy'])
        ->name('admin.status-consentimentos.destroy');

    Route::get('admin/status-comerciais', [StatusComercialController::class, 'index'])
        ->name('admin.status-comerciais.index');
    Route::post('admin/status-comerciais', [StatusComercialController::class, 'store'])
        ->name('admin.status-comerciais.store');
    Route::get('admin/status-comerciais/{status_comercial}/edit', [StatusComercialController::class, 'edit'])
        ->name('admin.status-comerciais.edit');
    Route::patch('admin/status-comerciais/{status_comercial}', [StatusComercialController::class, 'update'])
        ->name('admin.status-comerciais.update');
    Route::delete('admin/status-comerciais/{status_comercial}', [StatusComercialController::class, 'destroy'])
        ->name('admin.status-comerciais.destroy');

    Route::get('admin/setores', [SetorController::class, 'index'])->name('admin.setores.index');
    Route::post('admin/setores', [SetorController::class, 'store'])->name('admin.setores.store');
    Route::get('admin/setores/{setor}/edit', [SetorController::class, 'edit'])->name('admin.setores.edit');
    Route::patch('admin/setores/{setor}', [SetorController::class, 'update'])->name('admin.setores.update');
    Route::delete('admin/setores/{setor}', [SetorController::class, 'destroy'])->name('admin.setores.destroy');

    Route::get('admin/canais-contato', [CanalContatoController::class, 'index'])->name('admin.canais-contato.index');
    Route::post('admin/canais-contato', [CanalContatoController::class, 'store'])->name('admin.canais-contato.store');
    Route::get('admin/canais-contato/{canal_contato}/edit', [CanalContatoController::class, 'edit'])->name('admin.canais-contato.edit');
    Route::patch('admin/canais-contato/{canal_contato}', [CanalContatoController::class, 'update'])->name('admin.canais-contato.update');
    Route::delete('admin/canais-contato/{canal_contato}', [CanalContatoController::class, 'destroy'])->name('admin.canais-contato.destroy');

    Route::get('admin/status-conflitos', [StatusConflitoController::class, 'index'])->name('admin.status-conflitos.index');
    Route::post('admin/status-conflitos', [StatusConflitoController::class, 'store'])->name('admin.status-conflitos.store');
    Route::get('admin/status-conflitos/{status_conflito}/edit', [StatusConflitoController::class, 'edit'])->name('admin.status-conflitos.edit');
    Route::patch('admin/status-conflitos/{status_conflito}', [StatusConflitoController::class, 'update'])->name('admin.status-conflitos.update');
    Route::delete('admin/status-conflitos/{status_conflito}', [StatusConflitoController::class, 'destroy'])->name('admin.status-conflitos.destroy');

    Route::get('admin/tipos-pessoa', [TipoPessoaController::class, 'index'])->name('admin.tipos-pessoa.index');
    Route::post('admin/tipos-pessoa', [TipoPessoaController::class, 'store'])->name('admin.tipos-pessoa.store');
    Route::get('admin/tipos-pessoa/{tipo_pessoa}/edit', [TipoPessoaController::class, 'edit'])->name('admin.tipos-pessoa.edit');
    Route::patch('admin/tipos-pessoa/{tipo_pessoa}', [TipoPessoaController::class, 'update'])->name('admin.tipos-pessoa.update');
    Route::delete('admin/tipos-pessoa/{tipo_pessoa}', [TipoPessoaController::class, 'destroy'])->name('admin.tipos-pessoa.destroy');

    Route::get('admin/funis', [FunilController::class, 'index'])->name('admin.funis.index');
    Route::post('admin/funis', [FunilController::class, 'store'])->name('admin.funis.store');
    Route::get('admin/funis/{funil}/edit', [FunilController::class, 'edit'])->name('admin.funis.edit');
    Route::patch('admin/funis/{funil}', [FunilController::class, 'update'])->name('admin.funis.update');
    Route::delete('admin/funis/{funil}', [FunilController::class, 'destroy'])->name('admin.funis.destroy');

    Route::post('admin/funis/{funil}/etapas', [EtapaFunilController::class, 'store'])->name('admin.funis.etapas.store');
    Route::get('admin/funis/{funil}/etapas/{etapa}/edit', [EtapaFunilController::class, 'edit'])->name('admin.funis.etapas.edit');
    Route::patch('admin/funis/{funil}/etapas/{etapa}', [EtapaFunilController::class, 'update'])->name('admin.funis.etapas.update');
    Route::delete('admin/funis/{funil}/etapas/{etapa}', [EtapaFunilController::class, 'destroy'])->name('admin.funis.etapas.destroy');
});

require __DIR__.'/settings.php';
