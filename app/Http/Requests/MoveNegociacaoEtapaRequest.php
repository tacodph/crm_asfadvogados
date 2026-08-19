<?php

namespace App\Http\Requests;

use App\Models\Negociacao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveNegociacaoEtapaRequest extends FormRequest
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
        /** @var Negociacao $negociacao */
        $negociacao = $this->route('negociacao');

        return [
            'etapa_funil_id' => [
                'required',
                'integer',
                Rule::exists('etapas_funil', 'id')->where('funil_id', $negociacao->funil_id),
            ],
        ];
    }
}
