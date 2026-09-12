<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->foreignId('status_atendimento_id')
                ->nullable()
                ->after('canal_contato_id')
                ->constrained('status_atendimentos')
                ->nullOnDelete();

            $table->foreignId('status_qualificacao_id')
                ->nullable()
                ->after('status_atendimento_id')
                ->constrained('status_qualificacoes')
                ->nullOnDelete();

            $table->text('motivo_desqualificacao')->nullable()->after('status_qualificacao_id');
            $table->string('continuidade_atendimento')->nullable()->after('motivo_desqualificacao');
            $table->text('observacoes_complementares')->nullable()->after('continuidade_atendimento');
        });
    }

    public function down(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_atendimento_id');
            $table->dropConstrainedForeignId('status_qualificacao_id');
            $table->dropColumn([
                'motivo_desqualificacao',
                'continuidade_atendimento',
                'observacoes_complementares',
            ]);
        });
    }
};
