<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesContatoDeduplicacao;
use App\Models\IbgeMunicipio;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContatoRequest extends FormRequest
{
    use ValidatesContatoDeduplicacao;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cep' => ['nullable', 'string', 'max:9'],
            'cpf' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('contatos', 'cpf')->where('tenant_id', $tenantId),
            ],
            'tipo_pessoa_id' => ['required', 'integer', Rule::exists('tipos_pessoa', 'id')],
            'empresa_id' => [
                'nullable',
                'integer',
                Rule::exists('empresas', 'id')->where('tenant_id', $tenantId),
            ],
            'municipio_id' => [
                'nullable',
                'integer',
                Rule::exists(IbgeMunicipio::class, 'id'),
            ],
            'canal_contato_id' => [
                'required',
                'integer',
                Rule::exists('canais_contato', 'id')->where('tenant_id', $tenantId),
            ],
            'status_consentimento_id' => [
                'required',
                'integer',
                Rule::exists('status_consentimentos', 'id')->where('tenant_id', $tenantId),
            ],
            'status_comercial_id' => [
                'required',
                'integer',
                Rule::exists('status_comerciais', 'id')->where('tenant_id', $tenantId),
            ],
            'consentimentos' => ['required', 'array', 'min:1'],
            'consentimentos.*.finalidade_consentimento_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('finalidades_consentimento', 'id')->where('tenant_id', $tenantId),
            ],
            'consentimentos.*.status_consentimento_id' => [
                'required',
                'integer',
                Rule::exists('status_consentimentos', 'id')->where('tenant_id', $tenantId),
            ],
            'consentimentos.*.concedido_em' => ['nullable', 'date'],
            'consentimentos.*.revogado_em' => ['nullable', 'date', 'after_or_equal:consentimentos.*.concedido_em'],
            'return_to_empresa' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->addContatoDeduplicacaoValidator($validator);
    }

    protected function prepareForValidation(): void
    {
        $consentimentos = collect($this->input('consentimentos', []))
            ->map(function (mixed $linha): array {
                $linha = is_array($linha) ? $linha : [];

                return [
                    'finalidade_consentimento_id' => isset($linha['finalidade_consentimento_id'])
                        ? (int) $linha['finalidade_consentimento_id']
                        : null,
                    'status_consentimento_id' => isset($linha['status_consentimento_id'])
                        ? (int) $linha['status_consentimento_id']
                        : null,
                    'concedido_em' => filled($linha['concedido_em'] ?? null) ? $linha['concedido_em'] : null,
                    'revogado_em' => filled($linha['revogado_em'] ?? null) ? $linha['revogado_em'] : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'cargo' => $this->filled('cargo') ? $this->string('cargo')->toString() : null,
            'cep' => $this->filled('cep') ? $this->string('cep')->toString() : null,
            'empresa_id' => $this->filled('empresa_id') ? $this->integer('empresa_id') : null,
            'municipio_id' => $this->filled('municipio_id') ? $this->integer('municipio_id') : null,
            'consentimentos' => $consentimentos,
        ]);

        $this->mergeNormalizedContatoIdentifiers();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do contato.',
            'email.email' => 'Informe um e-mail válido.',
            'cpf.unique' => 'Já existe um contato com este CPF neste escritório.',
            'tipo_pessoa_id.required' => 'Selecione o tipo de pessoa.',
            'canal_contato_id.required' => 'Selecione o canal preferido.',
            'status_consentimento_id.required' => 'Selecione o status de consentimento.',
            'status_comercial_id.required' => 'Selecione o status comercial.',
            'consentimentos.required' => 'Informe o consentimento por finalidade.',
        ];
    }

    /**
     * @return array{contato: array<string, mixed>, consentimentos: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'contato' => [
                ...collect($validated)->except(['consentimentos', 'return_to_empresa'])->all(),
                'registro_mesclado' => false,
                'observacao_deduplicacao' => null,
            ],
            'consentimentos' => $validated['consentimentos'],
        ];
    }
}
