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
        Schema::create('historicos_negociacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negociacao_id')->index()->constrained('negociacoes')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('titulo');
            $table->text('descricao');
            $table->string('autor');
            $table->timestamp('ocorrido_em')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historicos_negociacao');
    }
};
