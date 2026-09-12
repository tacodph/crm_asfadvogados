<?php

use App\Enums\StatusTarefaNegociacao;
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
        Schema::create('tarefas_negociacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('negociacao_id')->index()->constrained('negociacoes')->cascadeOnDelete();
            $table->string('descricao');
            $table->date('data')->index();
            $table->time('hora')->nullable();
            $table->string('status')->default(StatusTarefaNegociacao::Pendente->value)->index();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $negociacoes = DB::table('negociacoes')
            ->whereNotNull('proxima_tarefa')
            ->where('proxima_tarefa', '!=', '')
            ->whereNotNull('proxima_tarefa_em')
            ->get(['id', 'tenant_id', 'proxima_tarefa', 'proxima_tarefa_em', 'proxima_tarefa_hora', 'created_at', 'updated_at']);

        $agora = now();

        foreach ($negociacoes as $negociacao) {
            DB::table('tarefas_negociacao')->insert([
                'tenant_id' => $negociacao->tenant_id,
                'negociacao_id' => $negociacao->id,
                'descricao' => $negociacao->proxima_tarefa,
                'data' => $negociacao->proxima_tarefa_em,
                'hora' => $negociacao->proxima_tarefa_hora,
                'status' => StatusTarefaNegociacao::Pendente->value,
                'criado_por_user_id' => null,
                'created_at' => $negociacao->created_at ?? $agora,
                'updated_at' => $negociacao->updated_at ?? $agora,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarefas_negociacao');
    }
};
