<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('negociacao_id')->constrained('negociacoes')->cascadeOnDelete();
            $table->foreignId('contato_id')->constrained('contatos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('autor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('codigo', 32);
            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('titulo');
            $table->text('escopo');
            $table->decimal('honorarios', 12, 2);
            $table->string('parcelamento')->nullable();
            $table->string('indice_reajuste')->nullable();
            $table->string('modelo_origem')->nullable();
            $table->string('status')->index();
            $table->date('valido_ate');
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('aceito_em')->nullable();
            $table->json('clausulas')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo', 'versao']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'negociacao_id']);
            $table->index(['tenant_id', 'valido_ate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas');
    }
};
