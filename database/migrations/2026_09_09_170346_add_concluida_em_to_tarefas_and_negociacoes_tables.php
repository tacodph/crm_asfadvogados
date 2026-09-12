<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tarefas_negociacao', function (Blueprint $table) {
            $table->timestamp('concluida_em')->nullable()->index()->after('status');
        });

        Schema::table('negociacoes', function (Blueprint $table) {
            $table->timestamp('concluida_em')->nullable()->index()->after('etapa_desde');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tarefas_negociacao', function (Blueprint $table) {
            $table->dropColumn('concluida_em');
        });

        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropColumn('concluida_em');
        });
    }
};
