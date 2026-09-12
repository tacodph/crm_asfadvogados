<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string}>
     */
    private function defaults(): array
    {
        return [
            [
                'slug' => 'aguardando-retorno-1-contato',
                'nome' => 'Aguardando Retorno do 1º contato',
                'cor_fundo' => '#DBEAFE',
                'cor_texto' => '#1D4ED8',
            ],
            [
                'slug' => 'qualificado',
                'nome' => 'Qualificado',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'desqualificado',
                'nome' => 'Desqualificado',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
            ],
        ];
    }

    public function up(): void
    {
        Schema::create('status_qualificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('nome');
            $table->string('cor_fundo', 9);
            $table->string('cor_texto', 9);
            $table->unsignedSmallInteger('ordem')->default(0)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'nome']);
        });

        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($this->defaults() as $ordem => $status) {
                DB::table('status_qualificacoes')->insert([
                    'tenant_id' => $tenantId,
                    'slug' => $status['slug'],
                    'nome' => $status['nome'],
                    'cor_fundo' => $status['cor_fundo'],
                    'cor_texto' => $status['cor_texto'],
                    'ordem' => $ordem + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_qualificacoes');
    }
};
