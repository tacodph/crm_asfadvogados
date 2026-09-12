<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'municipio_id')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->unsignedInteger('municipio_id')->nullable()->after('uf_id')->index();
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conname = \'empresas_municipio_id_foreign\'
                    ) THEN
                        ALTER TABLE empresas
                        ADD CONSTRAINT empresas_municipio_id_foreign
                        FOREIGN KEY (municipio_id)
                        REFERENCES base_dados_ibge.tab_municipios (id);
                    END IF;
                END $$;
            ');

            DB::statement('
                UPDATE empresas AS e
                SET municipio_id = m.id
                FROM ufs AS u
                INNER JOIN base_dados_ibge.tab_estados AS est
                    ON UPPER(est.txt_sigla_uf) = UPPER(u.sigla)
                INNER JOIN base_dados_ibge.tab_municipios AS m
                    ON m.estado_id = est.id
                WHERE e.uf_id = u.id
                  AND e.municipio_id IS NULL
                  AND LOWER(m.txt_nome_municipios) = LOWER(e.cidade)
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE empresas DROP CONSTRAINT IF EXISTS empresas_municipio_id_foreign');
        }

        if (Schema::hasColumn('empresas', 'municipio_id')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('municipio_id');
            });
        }
    }
};
