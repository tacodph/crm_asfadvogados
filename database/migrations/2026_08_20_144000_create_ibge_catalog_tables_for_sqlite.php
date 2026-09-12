<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures IBGE catalog tables exist for SQLite tests. On PostgreSQL the
 * schema/tables are already provisioned in `asf_advogados.base_dados_ibge`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS base_dados_ibge');

            return;
        }

        if (! Schema::hasTable('tab_estados')) {
            Schema::create('tab_estados', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('txt_uf');
                $table->string('txt_sigla_uf')->nullable();
                $table->string('txt_gentilico')->nullable();
                $table->string('txt_nome_governador')->nullable();
                $table->string('txt_nome_capital')->nullable();
                $table->decimal('vlr_area_territorial_km2', 14, 4)->nullable();
                $table->integer('num_populacao_ultimo_censo')->nullable();
                $table->decimal('vlr_densidade_demografica_hab_km2', 14, 4)->nullable();
                $table->integer('num_populacao_estimada')->nullable();
                $table->decimal('vlr_idh', 8, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tab_municipios')) {
            Schema::create('tab_municipios', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('txt_nome_municipios')->nullable();
                $table->integer('cod_municipio_6dig')->nullable()->index();
                $table->integer('cod_regiao_geografica_imediata')->nullable();
                $table->integer('cod_regiao_geografica_intermediaria')->nullable();
                $table->integer('estado_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return;
        }

        Schema::dropIfExists('tab_municipios');
        Schema::dropIfExists('tab_estados');
    }
};
