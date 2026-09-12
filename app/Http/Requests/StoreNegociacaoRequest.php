<?php

namespace App\Http\Requests;

use App\Models\Contato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNegociacaoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'responsavel_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assunto' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0'],
            'previsao_fechamento' => ['nullable', 'date'],
            'proxima_tarefa' => ['nullable', 'string', 'max:255'],
            'proxima_tarefa_em' => ['nullable', 'date'],
            'proxima_tarefa_hora' => ['nullable', 'date_format:H:i'],
            // Parâmetros do Pixel do navegador (opcionais, vindos do formulário de captação).
            'meta_event_id' => ['nullable', 'string', 'max:255'],
            'meta_fbp' => ['nullable', 'string', 'max:255'],
            'meta_fbc' => ['nullable', 'string', 'max:255'],
            'meta_event_source_url' => ['nullable', 'string', 'max:2048'],
            // Atribuição Meta Ads (opcionais) — o controller junta em origem_utm.
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'fbclid' => ['nullable', 'string', 'max:512'],
            'meta_ad_id' => ['nullable', 'string', 'max:64'],
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
}
