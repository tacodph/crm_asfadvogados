<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evento da API de Conversões que esta etapa dispara quando a negociação
     * entra nela (ex.: "Schedule", "Purchase"). Nulo = nada dispara. Substitui
     * o casamento por trecho do nome em config('meta.capi.mapa_etapa_evento'),
     * que fica como legado.
     */
    public function up(): void
    {
        Schema::table('etapas_funil', function (Blueprint $table) {
            $table->string('meta_evento', 40)->nullable()->after('campos');
        });
    }

    public function down(): void
    {
        Schema::table('etapas_funil', function (Blueprint $table) {
            $table->dropColumn('meta_evento');
        });
    }
};
