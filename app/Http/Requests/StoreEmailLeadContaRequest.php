<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmailLeadConta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmailLeadContaRequest extends FormRequest
{
    use ValidatesEmailLeadConta;

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
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararConta();
    }
}
