<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $txt_uf
 * @property string|null $txt_sigla_uf
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IbgeEstado extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return list<string>
     */
    protected $fillable = [
        'id',
        'txt_uf',
        'txt_sigla_uf',
        'txt_gentilico',
        'txt_nome_governador',
        'txt_nome_capital',
        'vlr_area_territorial_km2',
        'num_populacao_ultimo_censo',
        'vlr_densidade_demografica_hab_km2',
        'num_populacao_estimada',
        'vlr_idh',
    ];

    public function getTable(): string
    {
        $driver = DB::connection()->getDriverName();
        $database = env('DB_IBGE_DATABASE');

        if ($driver === 'pgsql') {
            return ($database ?: 'base_dados_ibge').'.tab_estados';
        }

        if ($driver === 'mysql' && $database) {
            return "{$database}.tab_estados";
        }

        return 'tab_estados';
    }

    /**
     * @return HasMany<IbgeMunicipio, $this>
     */
    public function municipios(): HasMany
    {
        return $this->hasMany(IbgeMunicipio::class, 'estado_id');
    }
}
