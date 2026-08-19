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
        Schema::create('contatos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->index();
            $table->string('cargo')->nullable();
            $table->foreignId('empresa_id')->nullable()->index()->constrained('empresas')->nullOnDelete();
            $table->foreignId('tipo_pessoa_id')->index()->constrained('tipos_pessoa');
            $table->string('email')->nullable()->index();
            $table->string('telefone', 20)->nullable()->index();
            $table->string('cpf', 14)->nullable()->unique();
            $table->foreignId('canal_contato_id')->index()->constrained('canais_contato');
            $table->foreignId('status_consentimento_id')->index()->constrained('status_consentimentos');
            $table->boolean('registro_mesclado')->default(false);
            $table->string('observacao_deduplicacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contatos');
    }
};
