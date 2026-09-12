<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMetaCampanha;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMetaConversaoConfigRequest extends FormRequest
{
    use ValidatesMetaCampanha;

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
            ...$this->regrasCampanha(),
            'access_token' => ['required', 'string', 'min:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararCampanha();
    }
}
