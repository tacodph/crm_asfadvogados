<?php

use App\Http\Controllers\ContatoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\NegociacaoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('contatos', [ContatoController::class, 'index'])->name('contatos.index');
    Route::get('empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::get('negociacoes', [NegociacaoController::class, 'index'])->name('negociacoes.index');
    Route::patch('negociacoes/{negociacao}/etapa', [NegociacaoController::class, 'updateEtapa'])->name('negociacoes.update-etapa');
});

require __DIR__.'/settings.php';
