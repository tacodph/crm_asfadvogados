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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->index();
            $table->string('cnpj', 18)->unique();
            $table->foreignId('setor_id')->index()->constrained('setores');
            $table->string('porte');
            $table->string('cidade');
            $table->foreignId('uf_id')->index()->constrained('ufs');
            $table->foreignId('responsavel_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('status_conflito_id')->index()->constrained('status_conflitos');
            $table->text('conflito_texto')->nullable();
            $table->date('conflito_verificado_em')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
