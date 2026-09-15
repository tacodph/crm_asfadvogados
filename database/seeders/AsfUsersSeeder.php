<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;

/**
 * Equipe operacional do tenant asfadvogados, espelhada do banco de desenvolvimento.
 */
class AsfUsersSeeder extends Seeder
{
    use SeedsForDevTenant;

    public function run(): void
    {
        Role::ensureDefaults();

        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            foreach ($this->users() as $user) {
                User::query()->updateOrCreate(
                    ['email' => $user['email']],
                    [
                        'name' => $user['name'],
                        'password' => 'password',
                        'role' => $user['role'],
                        'especialidades' => $user['especialidades'],
                        'ausente_ate' => null,
                    ],
                );
            }
        });
    }

    /**
     * @return list<array{email: string, name: string, role: string, especialidades: list<string>}>
     */
    private function users(): array
    {
        return [
            [
                'email' => 'dev@asfadvogados.com.br',
                'name' => 'Sandro',
                'role' => Role::DEVELOPER,
                'especialidades' => [],
            ],
            [
                'email' => 'silasadauto@asfadvogados.com',
                'name' => 'Silas Adauto',
                'role' => Role::OWNER,
                'especialidades' => [],
            ],
            [
                'email' => 'vitoriasilva@asfadvogados.com',
                'name' => 'Vitória Silva',
                'role' => Role::MEMBER,
                'especialidades' => [],
            ],
            [
                'email' => 'daniellebatista@asfadvogados.com',
                'name' => 'Danielle Batista',
                'role' => Role::MEMBER,
                'especialidades' => [],
            ],
            [
                'email' => 'brunogabriel@asfadvogados.com',
                'name' => 'Bruno Gabriel',
                'role' => Role::ADMIN,
                'especialidades' => ['concursos'],
            ],
            [
                'email' => 'flavio.augusto@asfadvogados.com',
                'name' => 'Flávio Augusto',
                'role' => Role::VENDEDOR,
                'especialidades' => ['concursos'],
            ],
            [
                'email' => 'comercial.pj@asfadvogados.adv.br',
                'name' => 'Comercial PJ Meta Ads',
                'role' => Role::VENDEDOR,
                'especialidades' => ['gestao-passivo-pj', 'rev-pj'],
            ],
        ];
    }
}
