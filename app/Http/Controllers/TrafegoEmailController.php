<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailLeadContaRequest;
use App\Http\Requests\UpdateEmailLeadContaRequest;
use App\Models\EmailLeadConta;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;

/**
 * Aba `/trafego → E-mail`: CRUD das caixas de e-mail monitoradas pela
 * captura de leads (IMAP), com o funil de destino e a finalidade de
 * consentimento escolhidos pela equipe de tráfego. A `password` nunca volta
 * para o front — só o suficiente pra saber que já foi definida.
 */
class TrafegoEmailController extends Controller
{
    public function index(): Response
    {
        $contas = EmailLeadConta::query()
            ->with(['funil:id,nome', 'atualizadoPor:id,name'])
            ->orderBy('nome')
            ->get();

        return Inertia::render('crm/TrafegoEmail', [
            'contas' => $contas->map(fn (EmailLeadConta $conta): array => $this->contaSegura($conta))->values()->all(),
            'funis' => Funil::query()->orderBy('ordem')->get(['id', 'nome']),
            'finalidades' => FinalidadeConsentimento::query()
                ->orderBy('nome')
                ->get(['slug', 'nome'])
                ->map(fn (FinalidadeConsentimento $f): array => ['slug' => $f->slug, 'nome' => $f->nome])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreEmailLeadContaRequest $request): RedirectResponse
    {
        $conta = EmailLeadConta::query()->create([
            ...$request->validated(),
            'atualizado_por_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Caixa de e-mail \"{$conta->nome}\" cadastrada.",
        ]);

        return redirect()->route('trafego.email.index');
    }

    public function update(UpdateEmailLeadContaRequest $request, EmailLeadConta $conta): RedirectResponse
    {
        $dados = $request->validated();

        // "Deixe em branco para manter": só troca a senha se veio valor novo.
        if (blank($dados['password'] ?? null)) {
            unset($dados['password']);
        }

        $conta->update([...$dados, 'atualizado_por_user_id' => $request->user()?->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Caixa de e-mail \"{$conta->nome}\" atualizada.",
        ]);

        return redirect()->route('trafego.email.index');
    }

    public function destroy(EmailLeadConta $conta): RedirectResponse
    {
        $nome = $conta->nome;
        $conta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Caixa de e-mail \"{$nome}\" removida."]);

        return redirect()->route('trafego.email.index');
    }

    public function testarConexao(EmailLeadConta $conta, ClientManager $imap): RedirectResponse
    {
        try {
            $imap->make($conta->credenciaisImap())->connect();

            $conta->forceFill(['ultimo_status' => 'ok', 'ultimo_erro' => null])->saveQuietly();

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexão IMAP OK.']);
        } catch (AuthFailedException|ConnectionFailedException|ImapServerErrorException $e) {
            $mensagem = $this->mensagemErroImap($e);

            $conta->forceFill(['ultimo_status' => 'erro', 'ultimo_erro' => $mensagem])->saveQuietly();

            Log::channel('leads-email')->warning('Teste de conexão IMAP falhou', [
                'conta_id' => $conta->id,
                'erro' => $e->getMessage(),
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Falha na conexão: '.$mensagem]);
        }

        return redirect()->route('trafego.email.index');
    }

    private function mensagemErroImap(AuthFailedException|ConnectionFailedException|ImapServerErrorException $e): string
    {
        $mensagem = $e->getMessage();

        if (
            str_contains($mensagem, 'AUTHENTICATIONFAILED')
            || str_contains(mb_strtolower($mensagem), 'invalid credentials')
        ) {
            return 'Credenciais rejeitadas pelo Google. Use senha de app do Workspace (não a senha normal da conta): Conta Google → Segurança → Senhas de app. Confirme também que o admin liberou IMAP e senhas de app.';
        }

        return $mensagem;
    }

    /**
     * @return array<string, mixed>
     */
    private function contaSegura(EmailLeadConta $conta): array
    {
        return [
            'id' => $conta->id,
            'nome' => $conta->nome,
            'host' => $conta->host,
            'port' => $conta->port,
            'encryption' => $conta->encryption,
            'username' => $conta->username,
            'senha_definida' => $conta->password !== null,
            'pasta' => $conta->pasta,
            'funil_id' => $conta->funil_id,
            'funil_nome' => $conta->funil?->nome,
            'finalidade_consentimento_slug' => $conta->finalidade_consentimento_slug,
            'ativo' => $conta->ativo,
            'ultima_captura_em' => $conta->ultima_captura_em?->toIso8601String(),
            'ultimo_status' => $conta->ultimo_status,
            'ultimo_erro' => $conta->ultimo_erro,
            'atualizado_por' => $conta->atualizadoPor?->name,
        ];
    }
}
