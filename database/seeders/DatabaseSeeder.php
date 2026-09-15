<?php

namespace Database\Seeders;

use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Seed the application's database.
     *
     * Espelha o banco de desenvolvimento: catálogos + equipe ASF +
     * importações Qualificação (funil PF) e Meta Ads PJ.
     */
    public function run(): void
    {
        app(CurrentTenant::class)->set($this->devTenant());

        $this->call([
            DominioCrmSeeder::class,
            AsfUsersSeeder::class,
            QualificacaoAtendimentosSeeder::class,
            MetaAdsPjSeeder::class,
        ]);
    }
}
