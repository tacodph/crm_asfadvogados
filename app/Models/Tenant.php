<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $plan
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property string|null $trafego_lead_token_hash
 * @property string|null $trafego_lead_token_ultimos4
 * @property Carbon|null $trafego_lead_token_gerado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('tenants')]
#[Fillable(['name', 'slug', 'plan', 'status', 'trial_ends_at'])]
#[Hidden(['trafego_lead_token_hash'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'trafego_lead_token_gerado_em' => 'datetime',
        ];
    }

    /**
     * (Re)gera o segredo de captação de leads pelo site. Persiste só o hash;
     * o valor cru retornado é a única cópia — não é recuperável depois.
     */
    public function gerarTokenLeadTrafego(): string
    {
        $raw = 'asf_'.Str::random(48);

        $this->forceFill([
            'trafego_lead_token_hash' => hash('sha256', $raw),
            'trafego_lead_token_ultimos4' => substr($raw, -4),
            'trafego_lead_token_gerado_em' => now(),
        ])->save();

        return $raw;
    }

    public static function pelaChaveLeadTrafego(string $raw): ?self
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        return static::query()
            ->where('trafego_lead_token_hash', hash('sha256', $raw))
            ->first();
    }

    public function mascararTokenLeadTrafego(): string
    {
        return $this->trafego_lead_token_ultimos4 !== null && $this->trafego_lead_token_ultimos4 !== ''
            ? '••••'.$this->trafego_lead_token_ultimos4
            : '—';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
