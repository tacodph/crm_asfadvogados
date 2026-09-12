<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $txt_nome_municipios
 * @property int|null $cod_municipio_6dig
 * @property int|null $estado_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IbgeMunicipio extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return list<string>
     */
    protected $fillable = [
        'id',
        'txt_nome_municipios',
        'cod_municipio_6dig',
        'cod_regiao_geografica_imediata',
        'cod_regiao_geografica_intermediaria',
        'estado_id',
    ];

    public function getTable(): string
    {
        $driver = DB::connection()->getDriverName();
        $database = env('DB_IBGE_DATABASE');

        if ($driver === 'pgsql') {
            return ($database ?: 'base_dados_ibge').'.tab_municipios';
        }

        if ($driver === 'mysql' && $database) {
            return "{$database}.tab_municipios";
        }

        return 'tab_municipios';
    }

    /**
     * @return BelongsTo<IbgeEstado, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(IbgeEstado::class, 'estado_id');
    }
}
