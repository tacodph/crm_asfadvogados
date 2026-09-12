<?php

namespace App\Actions\Crm;

class NormalizeContatoIdentifiers
{
    public function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    public function telefone(?string $telefone): ?string
    {
        if ($telefone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $telefone) ?? '';

        return $digits === '' ? null : $digits;
    }

    public function cpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @param  array{email?: mixed, telefone?: mixed, cpf?: mixed}  $attributes
     * @return array{email: ?string, telefone: ?string, cpf: ?string}
     */
    public function __invoke(array $attributes): array
    {
        return [
            'email' => $this->email(isset($attributes['email']) ? (string) $attributes['email'] : null),
            'telefone' => $this->telefone(isset($attributes['telefone']) ? (string) $attributes['telefone'] : null),
            'cpf' => $this->cpf(isset($attributes['cpf']) ? (string) $attributes['cpf'] : null),
        ];
    }
}
