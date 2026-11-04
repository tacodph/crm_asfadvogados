<?php

use App\Enums\TipoTarefaNegociacao;
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
        Schema::table('tarefas_negociacao', function (Blueprint $table) {
            // Tarefas existentes eram, na prática, o "próximo contato" com o lead.
            $table->string('tipo', 20)->default(TipoTarefaNegociacao::FollowUp->value)->index()->after('descricao');
            $table->foreignId('responsavel_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('lembrete_enviado_em')->nullable()->after('concluida_em');
            $table->index(['tenant_id', 'responsavel_user_id', 'status', 'data'], 'tarefas_negociacao_agenda_index');
        });

        DB::table('tarefas_negociacao')->update([
            'responsavel_user_id' => DB::raw(
                '(select negociacoes.responsavel_user_id from negociacoes where negociacoes.id = tarefas_negociacao.negociacao_id)'
            ),
        ]);

        Schema::table('negociacoes', function (Blueprint $table) {
            $table->timestamp('ultimo_contato_em')->nullable()->index()->after('concluida_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropIndex(['ultimo_contato_em']);
            $table->dropColumn('ultimo_contato_em');
        });

        Schema::table('tarefas_negociacao', function (Blueprint $table) {
            $table->dropIndex('tarefas_negociacao_agenda_index');
            $table->dropConstrainedForeignId('responsavel_user_id');
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'lembrete_enviado_em']);
        });
    }
};
