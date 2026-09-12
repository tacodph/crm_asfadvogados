<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->company();

        return [
            'name' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 999999),
            'plan' => fake()->randomElement(['essencial', 'escritorio', 'banca']),
            'status' => 'active',
            'trial_ends_at' => null,
        ];
    }

    /**
     * Indicate that the tenant is on an active trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
