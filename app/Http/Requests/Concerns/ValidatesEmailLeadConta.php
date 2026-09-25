<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas pelo cadastro/edição de caixas de e-mail (tela
 * `/trafego → E-mail`). O `password` fica a cargo de cada FormRequest, já
 * que varia entre obrigatório (criar) e opcional (editar).
 */
trait ValidatesEmailLeadConta
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function regrasConta(?int $ignorarContaId = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'string', Rule::in(['ssl', 'tls', 'notls'])],
            'username' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_leads_contas', 'username')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignorarContaId),
            ],
            'pasta' => ['required', 'string', 'max:255'],
            'funil_id' => [
                'required',
                'integer',
                Rule::exists('funis', 'id')->where('tenant_id', $tenantId),
            ],
            'finalidade_consentimento_slug' => [
                'required',
                'string',
                Rule::exists('finalidades_consentimento', 'slug')->where('tenant_id', $tenantId),
            ],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * Normaliza os defaults dos selects para não recusar submissões parciais.
     */
    protected function prepararConta(): void
    {
        $this->merge([
            'nome' => $this->string('nome')->trim()->toString(),
            'host' => $this->filled('host') ? $this->string('host')->trim()->toString() : 'imap.gmail.com',
            'port' => $this->filled('port') ? (int) $this->input('port') : 993,
            'encryption' => $this->filled('encryption') ? $this->string('encryption')->toString() : 'ssl',
            'pasta' => $this->filled('pasta') ? $this->string('pasta')->trim()->toString() : 'INBOX',
            'ativo' => $this->boolean('ativo', true),
            // Senhas de app do Google vêm como "xxxx xxxx xxxx xxxx"; o IMAP exige sem espaços.
            'password' => $this->filled('password')
                ? preg_replace('/\s+/', '', $this->string('password')->toString())
                : $this->input('password'),
        ]);
    }
}
