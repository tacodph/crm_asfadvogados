<?php

namespace App\Http\Requests;

use App\Enums\StatusTarefaNegociacao;
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
            'data' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::enum(StatusTarefaNegociacao::class)],
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
        ];
    }
}
