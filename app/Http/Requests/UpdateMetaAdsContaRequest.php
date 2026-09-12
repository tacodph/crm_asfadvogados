<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMetaAdsConta;
use App\Models\MetaAdsConta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaAdsContaRequest extends FormRequest
{
    use ValidatesMetaAdsConta;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var MetaAdsConta $conta */
        $conta = $this->route('conta');

        return [
            ...$this->regrasConta($conta->id),
            // Em branco = manter o token atual (o controller descarta a chave).
            'access_token' => ['nullable', 'string', 'min:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararConta();
    }
}
