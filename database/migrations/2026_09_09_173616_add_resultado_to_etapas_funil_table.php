<?php

use App\Enums\EtapaFunilResultado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('etapas_funil', function (Blueprint $table) {
            $table->string('resultado', 16)
                ->default(EtapaFunilResultado::Aberta->value)
                ->after('exige_motivo')
                ->index();
        });

        // Etapas que hoje são a última do funil passam a ser "ganho"
        // (Fechamento), antes de criarmos a coluna vermelha de encerrados.
        $funilIds = DB::table('etapas_funil')->distinct()->pluck('funil_id');

        foreach ($funilIds as $funilId) {
            $maxOrdem = DB::table('etapas_funil')
                ->where('funil_id', $funilId)
                ->max('ordem');

            if ($maxOrdem === null) {
                continue;
            }

            DB::table('etapas_funil')
                ->where('funil_id', $funilId)
                ->where('ordem', $maxOrdem)
                ->update(['resultado' => EtapaFunilResultado::Ganho->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('etapas_funil', function (Blueprint $table) {
            $table->dropColumn('resultado');
        });
    }
};
