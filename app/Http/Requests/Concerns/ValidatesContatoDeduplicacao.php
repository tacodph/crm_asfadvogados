<?php

namespace App\Http\Requests\Concerns;

use App\Actions\Crm\FindContatoDuplicatas;
use App\Actions\Crm\NormalizeContatoIdentifiers;
use App\Models\Contato;
use Illuminate\Validation\Validator;

trait ValidatesContatoDeduplicacao
{
    protected function mergeNormalizedContatoIdentifiers(): void
    {
        $normalize = app(NormalizeContatoIdentifiers::class);
        $normalized = [];

        if ($this->exists('email')) {
            $normalized['email'] = $normalize->email(
                $this->filled('email') ? $this->string('email')->toString() : null,
            );
        }

        if ($this->exists('telefone')) {
            $normalized['telefone'] = $normalize->telefone(
                $this->filled('telefone') ? $this->string('telefone')->toString() : null,
            );
        }

        if ($this->exists('cpf')) {
            $normalized['cpf'] = $normalize->cpf(
                $this->filled('cpf') ? $this->string('cpf')->toString() : null,
            );
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    protected function addContatoDeduplicacaoValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Contato|null $contato */
            $contato = $this->route('contato');

            $matches = app(FindContatoDuplicatas::class)(
                email: $this->input('email'),
                telefone: $this->input('telefone'),
                cpf: $this->input('cpf'),
                ignoreId: $contato?->id,
            );

            foreach ($matches as $match) {
                if (! $validator->errors()->has($match['campo'])) {
                    $validator->errors()->add($match['campo'], $match['mensagem']);
                }
            }
        });
    }
}
