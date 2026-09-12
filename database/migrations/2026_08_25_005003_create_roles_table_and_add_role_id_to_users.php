<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<array{slug: string, nome: string, descricao: string}>
     */
    private function defaults(): array
    {
        return [
            [
                'slug' => 'owner',
                'nome' => 'Responsável pela conta',
                'descricao' => 'Dono do escritório no CRM; gerencia usuários e configurações do tenant.',
            ],
            [
                'slug' => 'admin',
                'nome' => 'Administrador',
                'descricao' => 'Administra catálogos, usuários e operações do escritório.',
            ],
            [
                'slug' => 'developer',
                'nome' => 'Desenvolvedor',
                'descricao' => 'Acesso técnico de suporte e manutenção do sistema.',
            ],
            [
                'slug' => 'gerente',
                'nome' => 'Gerente',
                'descricao' => 'Lidera a operação comercial: funil, equipe e indicadores.',
            ],
            [
                'slug' => 'vendedor',
                'nome' => 'Vendedor',
                'descricao' => 'Atua no dia a dia comercial: contatos, empresas e negociações.',
            ],
            [
                'slug' => 'member',
                'nome' => 'Membro',
                'descricao' => 'Acesso padrão às funcionalidades comerciais do CRM.',
            ],
        ];
    }

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome')->unique();
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0)->index();
            $table->timestamps();
        });

        $now = now();
        $roleIdsBySlug = [];

        foreach ($this->defaults() as $ordem => $role) {
            $roleIdsBySlug[$role['slug']] = DB::table('roles')->insertGetId([
                'slug' => $role['slug'],
                'nome' => $role['nome'],
                'descricao' => $role['descricao'],
                'ordem' => $ordem + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('password')
                ->constrained('roles');
        });

        $memberId = $roleIdsBySlug['member'];

        foreach (DB::table('users')->select(['id', 'role'])->get() as $user) {
            $slug = is_string($user->role) && $user->role !== ''
                ? $user->role
                : 'member';

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'role_id' => $roleIdsBySlug[$slug] ?? $memberId,
                ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index()->after('password');
        });

        $slugsById = DB::table('roles')->pluck('slug', 'id');

        foreach (DB::table('users')->select(['id', 'role_id'])->get() as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'role' => $slugsById[$user->role_id] ?? 'member',
                ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
