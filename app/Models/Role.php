<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Global user roles (owner, admin, gerente, vendedor, …).
 *
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string|null $descricao
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('roles')]
#[Fillable(['slug', 'nome', 'descricao', 'ordem'])]
class Role extends Model
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const DEVELOPER = 'developer';

    public const GERENTE = 'gerente';

    public const VENDEDOR = 'vendedor';

    public const MEMBER = 'member';

    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function idFor(string $slug): int
    {
        $id = static::query()->where('slug', $slug)->value('id');

        if ($id === null) {
            throw new ModelNotFoundException(
                "No query results for role [{$slug}].",
            );
        }

        return (int) $id;
    }

    /**
     * Ensure the default catalog rows exist (tests / fresh installs).
     */
    public static function ensureDefaults(): void
    {
        $defaults = [
            ['slug' => self::OWNER, 'nome' => 'Responsável pela conta', 'descricao' => 'Dono do escritório no CRM; gerencia usuários e configurações do tenant.'],
            ['slug' => self::ADMIN, 'nome' => 'Administrador', 'descricao' => 'Administra catálogos, usuários e operações do escritório.'],
            ['slug' => self::DEVELOPER, 'nome' => 'Desenvolvedor', 'descricao' => 'Acesso técnico de suporte e manutenção do sistema.'],
            ['slug' => self::GERENTE, 'nome' => 'Gerente', 'descricao' => 'Lidera a operação comercial: funil, equipe e indicadores.'],
            ['slug' => self::VENDEDOR, 'nome' => 'Vendedor', 'descricao' => 'Atua no dia a dia comercial: contatos, empresas e negociações.'],
            ['slug' => self::MEMBER, 'nome' => 'Membro', 'descricao' => 'Acesso padrão às funcionalidades comerciais do CRM.'],
        ];

        foreach ($defaults as $ordem => $role) {
            static::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [...$role, 'ordem' => $ordem + 1],
            );
        }
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return static::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get()
            ->all();
    }
}
