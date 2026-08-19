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
        Schema::create('consentimentos_contato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contato_id')->index()->constrained('contatos')->cascadeOnDelete();
            $table->foreignId('finalidade_consentimento_id')->index()->constrained('finalidades_consentimento');
            $table->foreignId('status_consentimento_id')->index()->constrained('status_consentimentos');
            $table->date('concedido_em')->nullable();
            $table->date('revogado_em')->nullable();
            $table->timestamps();

            $table->unique(
                ['contato_id', 'finalidade_consentimento_id'],
                'consentimentos_contato_unico',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consentimentos_contato');
    }
};
