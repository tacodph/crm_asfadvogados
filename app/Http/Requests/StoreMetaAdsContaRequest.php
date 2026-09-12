<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMetaAdsConta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMetaAdsContaRequest extends FormRequest
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
        return [
            ...$this->regrasConta(),
            'access_token' => ['required', 'string', 'min:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararConta();
    }
}
