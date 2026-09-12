<?php

namespace App\Http\Requests;

use App\Models\Contato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNegociacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'funil_id' => ['required', 'integer', Rule::exists('funis', 'id')],
            'etapa_funil_id' => [
                'required',
                'integer',
                Rule::exists('etapas_funil', 'id')->where('funil_id', $this->integer('funil_id')),
            ],
            'contato_id' => ['required', 'integer', Rule::exists('contatos', 'id')],
            'empresa_id' => ['nullable', 'integer', Rule::exists('empresas', 'id')],
            'canal_contato_id' => ['required', 'integer', Rule::exists('canais_contato', 'id')],
            'status_atendimento_id' => ['nullable', 'integer', Rule::exists('status_atendimentos', 'id')],
            'status_qualificacao_id' => ['nullable', 'integer', Rule::exists('status_qualificacoes', 'id')],
            'motivo_desqualificacao' => ['nullable', 'string', 'max:2000'],
            'continuidade_atendimento' => ['nullable', 'string', 'max:2000'],
            'observacoes_complementares' => ['nullable', 'string', 'max:5000'],
            'responsavel_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assunto' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0'],
            'previsao_fechamento' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $contato = Contato::query()->find($this->integer('contato_id'));

                if ($contato === null) {
                    return;
                }

                $empresaId = $this->filled('empresa_id')
                    ? $this->integer('empresa_id')
                    : null;

                if (
                    $empresaId !== null
                    && $contato->empresa_id !== null
                    && $contato->empresa_id !== $empresaId
                ) {
                    $validator->errors()->add(
                        'contato_id',
                        'O contato selecionado não pertence à empresa informada.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'funil_id.required' => 'Selecione o funil.',
            'etapa_funil_id.required' => 'Selecione a etapa.',
            'contato_id.required' => 'Selecione o contato.',
            'canal_contato_id.required' => 'Selecione o canal.',
            'assunto.required' => 'Informe o assunto da negociação.',
            'valor.required' => 'Informe o valor.',
        ];
    }
}
