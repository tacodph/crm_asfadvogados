<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->jobTitle();

        return [
            'slug' => Str::slug($nome),
            'nome' => $nome,
            'descricao' => fake()->optional()->sentence(),
            'ordem' => fake()->numberBetween(1, 20),
        ];
    }
}
