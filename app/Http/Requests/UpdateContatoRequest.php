<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesContatoDeduplicacao;
use App\Models\Contato;
use App\Models\IbgeMunicipio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContatoRequest extends FormRequest
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
        /** @var Contato $contato */
        $contato = $this->route('contato');

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
                Rule::unique('contatos', 'cpf')
                    ->where(fn ($query) => $query->where('tenant_id', $contato->tenant_id))
                    ->ignore($contato->id),
            ],
            'tipo_pessoa_id' => ['required', 'integer', Rule::exists('tipos_pessoa', 'id')],
            'empresa_id' => [
                'nullable',
                'integer',
                Rule::exists('empresas', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'municipio_id' => [
                'nullable',
                'integer',
                Rule::exists(IbgeMunicipio::class, 'id'),
            ],
            'canal_contato_id' => [
                'required',
                'integer',
                Rule::exists('canais_contato', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'status_consentimento_id' => [
                'required',
                'integer',
                Rule::exists('status_consentimentos', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'status_comercial_id' => [
                'required',
                'integer',
                Rule::exists('status_comerciais', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'registro_mesclado' => ['required', 'boolean'],
            'observacao_deduplicacao' => ['nullable', 'string', 'max:255'],
            'consentimentos' => ['required', 'array', 'min:1'],
            'consentimentos.*.finalidade_consentimento_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('finalidades_consentimento', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'consentimentos.*.status_consentimento_id' => [
                'required',
                'integer',
                Rule::exists('status_consentimentos', 'id')->where('tenant_id', $contato->tenant_id),
            ],
            'consentimentos.*.concedido_em' => ['nullable', 'date'],
            'consentimentos.*.revogado_em' => ['nullable', 'date', 'after_or_equal:consentimentos.*.concedido_em'],
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
            'observacao_deduplicacao' => $this->filled('observacao_deduplicacao')
                ? $this->string('observacao_deduplicacao')->toString()
                : null,
            'registro_mesclado' => $this->boolean('registro_mesclado'),
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
            'tipo_pessoa_id.exists' => 'Tipo de pessoa inválido.',
            'canal_contato_id.required' => 'Selecione o canal preferido.',
            'canal_contato_id.exists' => 'Canal inválido.',
            'empresa_id.exists' => 'Empresa inválida.',
            'municipio_id.exists' => 'Município inválido.',
            'status_consentimento_id.required' => 'Selecione o status de consentimento.',
            'status_consentimento_id.exists' => 'Status de consentimento inválido.',
            'status_comercial_id.required' => 'Selecione o status comercial.',
            'status_comercial_id.exists' => 'Status comercial inválido.',
            'consentimentos.required' => 'Informe o consentimento por finalidade.',
            'consentimentos.*.finalidade_consentimento_id.required' => 'Finalidade inválida.',
            'consentimentos.*.status_consentimento_id.required' => 'Selecione o status da finalidade.',
        ];
    }

    /**
     * @return array{contato: array<string, mixed>, consentimentos: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'contato' => collect($validated)->except('consentimentos')->all(),
            'consentimentos' => $validated['consentimentos'],
        ];
    }
}
