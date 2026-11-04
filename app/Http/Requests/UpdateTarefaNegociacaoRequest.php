<?php

namespace App\Http\Requests;

use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTarefaNegociacaoRequest extends FormRequest
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
            'descricao' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', Rule::enum(TipoTarefaNegociacao::class)],
            'data' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::enum(StatusTarefaNegociacao::class)],
            'responsavel_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
            'proximo_follow_up_data' => ['nullable', 'date'],
            'proximo_follow_up_hora' => ['nullable', 'date_format:H:i'],
            'proximo_follow_up_descricao' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descricao.required' => 'Informe a descrição da tarefa.',
            'data.required' => 'Informe a data da tarefa.',
            'status.required' => 'Selecione o status da tarefa.',
            'responsavel_user_id.exists' => 'Selecione um responsável válido.',
        ];
    }
}
