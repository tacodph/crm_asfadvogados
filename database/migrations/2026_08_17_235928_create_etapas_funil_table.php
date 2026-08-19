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
        Schema::create('etapas_funil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funil_id')->index()->constrained('funis')->cascadeOnDelete();
            $table->string('nome');
            $table->string('sla');
            $table->json('campos');
            $table->boolean('exige_motivo')->default(false);
            $table->unsignedInteger('ordem')->default(1)->index();
            $table->string('cor_fundo', 16);
            $table->string('cor_texto', 16)->default('#FBF9F4');
            $table->string('cor_suave', 32)->default('rgba(251,249,244,0.9)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapas_funil');
    }
};
