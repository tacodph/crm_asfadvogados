<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Tenancy\CurrentTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $email
 * @property int $role_id
 * @property string|null $role slug accessor (owner, admin, …)
 * @property list<string>|null $especialidades
 * @property Carbon|null $ausente_ate
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $roleModel
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'role_id', 'role', 'especialidades', 'ausente_ate'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'especialidades' => 'array',
            'ausente_ate' => 'date',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->tenant_id ??= app(CurrentTenant::class)->id();
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Role slug accessor/mutator for mass assignment and Inertia props.
     *
     * @return Attribute<string|null, string|Role|null>
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->relationLoaded('roleModel')) {
                    return $this->roleModel?->slug;
                }

                if ($this->role_id === null) {
                    return null;
                }

                return $this->roleModel()->value('slug');
            },
            set: function (string|Role|null $value): array {
                if ($value instanceof Role) {
                    return ['role_id' => $value->id];
                }

                if ($value === null || $value === '') {
                    return ['role_id' => null];
                }

                return ['role_id' => Role::idFor($value)];
            },
        );
    }

    /**
     * @return HasMany<Empresa, $this>
     */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'responsavel_user_id');
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class, 'responsavel_user_id');
    }

    public function canManageTenantUsers(): bool
    {
        return in_array($this->role, [Role::OWNER, Role::ADMIN], true);
    }

    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }
}
