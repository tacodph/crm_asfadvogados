<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmailLeadConta;
use App\Models\EmailLeadConta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailLeadContaRequest extends FormRequest
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
        /** @var EmailLeadConta $conta */
        $conta = $this->route('conta');

        return [
            ...$this->regrasConta($conta->id),
            // "Deixe em branco para manter": vazio é aceito, o controller ignora.
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararConta();
    }
}
