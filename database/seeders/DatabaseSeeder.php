<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        });

        $this->call([
            QualificacaoAtendimentosSeeder::class,
        ]);
    }
}
