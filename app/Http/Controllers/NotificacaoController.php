<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    /**
     * Marca o lembrete como lido e abre a negociação dele.
     */
    public function abrir(Request $request, string $notificacao): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notificacao);
        $item->markAsRead();

        $negociacaoId = $item->data['negociacaoId'] ?? null;

        return $negociacaoId === null
            ? redirect()->route('meu-dia')
            : redirect()->route('negociacoes.edit', ['negociacao' => $negociacaoId]);
    }

    /**
     * Marca todos os lembretes do usuário como lidos.
     */
    public function marcarTodasComoLidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
