<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailLeadProcessado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailLeadProcessado>
 */
class EmailLeadProcessadoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => '<'.Str::uuid()->toString().'@mail.gmail.com>',
            'status' => 'pendente',
            'payload_html' => '<table><tr><td>Nome</td><td>Fake Lead</td></tr></table>',
        ];
    }

    public function processado(): static
    {
        return $this->state(fn (): array => ['status' => 'processado']);
    }

    public function comErro(string $erro = 'Falha simulada.'): static
    {
        return $this->state(fn (): array => ['status' => 'erro', 'erro' => $erro]);
    }
}
