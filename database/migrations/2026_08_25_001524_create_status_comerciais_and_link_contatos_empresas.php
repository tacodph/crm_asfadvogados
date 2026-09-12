<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default commercial statuses seeded per existing tenant on migrate.
     *
     * @return list<array{slug: string, nome: string, descricao: string, cor_fundo: string, cor_texto: string}>
     */
    private function defaults(): array
    {
        return [
            [
                'slug' => 'novo',
                'nome' => 'Novo',
                'descricao' => 'Cadastro recente, ainda sem triagem comercial.',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
            ],
            [
                'slug' => 'em-analise',
                'nome' => 'Em análise',
                'descricao' => 'Em triagem interna (perfil, conflito, interesse e fit).',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
            ],
            [
                'slug' => 'qualificado',
                'nome' => 'Qualificado',
                'descricao' => 'Apto a avançar no funil comercial com responsável atribuído.',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'em-negociacao',
                'nome' => 'Em negociação',
                'descricao' => 'Há negociação ativa em andamento com este cadastro.',
                'cor_fundo' => '#E8EEF6',
                'cor_texto' => '#3F5E8C',
            ],
            [
                'slug' => 'cliente-efetivado',
                'nome' => 'Cliente efetivado',
                'descricao' => 'Contrato assinado ou relacionamento de cliente consolidado.',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'inativo',
                'nome' => 'Inativo',
                'descricao' => 'Sem movimento recente; pode ser reativado se houver novo contato.',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
            ],
            [
                'slug' => 'desqualificado',
                'nome' => 'Desqualificado',
                'descricao' => 'Não encaixa no perfil de atuação do escritório neste momento.',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
            ],
        ];
    }

    public function up(): void
    {
        Schema::create('status_comerciais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('cor_fundo', 9);
            $table->string('cor_texto', 9);
            $table->unsignedSmallInteger('ordem')->default(0)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'nome']);
        });

        Schema::table('contatos', function (Blueprint $table) {
            $table->foreignId('status_comercial_id')
                ->nullable()
                ->after('status_consentimento_id')
                ->constrained('status_comerciais');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('status_comercial_id')
                ->nullable()
                ->after('status_conflito_id')
                ->constrained('status_comerciais');
        });

        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $novoId = null;

            foreach ($this->defaults() as $ordem => $status) {
                $id = DB::table('status_comerciais')->insertGetId([
                    'tenant_id' => $tenantId,
                    'slug' => $status['slug'],
                    'nome' => $status['nome'],
                    'descricao' => $status['descricao'],
                    'cor_fundo' => $status['cor_fundo'],
                    'cor_texto' => $status['cor_texto'],
                    'ordem' => $ordem + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($status['slug'] === 'novo') {
                    $novoId = $id;
                }
            }

            if ($novoId === null) {
                continue;
            }

            DB::table('contatos')
                ->where('tenant_id', $tenantId)
                ->whereNull('status_comercial_id')
                ->update(['status_comercial_id' => $novoId]);

            DB::table('empresas')
                ->where('tenant_id', $tenantId)
                ->whereNull('status_comercial_id')
                ->update(['status_comercial_id' => $novoId]);
        }

        Schema::table('contatos', function (Blueprint $table) {
            $table->unsignedBigInteger('status_comercial_id')->nullable(false)->change();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('status_comercial_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contatos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_comercial_id');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_comercial_id');
        });

        Schema::dropIfExists('status_comerciais');
    }
};
