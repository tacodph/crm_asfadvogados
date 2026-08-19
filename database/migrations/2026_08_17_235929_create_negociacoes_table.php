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
        Schema::create('negociacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funil_id')->index()->constrained('funis')->cascadeOnDelete();
            $table->foreignId('etapa_funil_id')->index()->constrained('etapas_funil')->restrictOnDelete();
            $table->foreignId('empresa_id')->nullable()->index()->constrained('empresas')->nullOnDelete();
            $table->foreignId('contato_id')->index()->constrained('contatos')->cascadeOnDelete();
            $table->foreignId('canal_contato_id')->index()->constrained('canais_contato');
            $table->foreignId('responsavel_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('assunto')->index();
            $table->decimal('valor', 12, 2);
            $table->date('previsao_fechamento')->nullable()->index();
            $table->timestamp('etapa_desde')->index();
            $table->string('proxima_tarefa')->nullable();
            $table->date('proxima_tarefa_em')->nullable()->index();
            $table->time('proxima_tarefa_hora')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negociacoes');
    }
};
