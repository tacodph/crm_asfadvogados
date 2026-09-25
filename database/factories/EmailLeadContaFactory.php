<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailLeadConta;
use App\Models\Funil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLeadConta>
 */
class EmailLeadContaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Leads Meta Ads',
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => fake()->unique()->safeEmail(),
            'password' => 'senha-de-app-'.fake()->unique()->numerify('####'),
            'pasta' => 'INBOX',
            'funil_id' => Funil::factory(),
            'finalidade_consentimento_slug' => 'contato-comercial',
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }
}
