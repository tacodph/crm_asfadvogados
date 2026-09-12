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
        Schema::table('users', function (Blueprint $table) {
            $table->json('especialidades')->nullable()->after('role');
            $table->date('ausente_ate')->nullable()->after('especialidades');
        });

        Schema::table('funis', function (Blueprint $table) {
            $table->foreignId('ultimo_responsavel_user_id')
                ->nullable()
                ->after('ordem')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ultimo_responsavel_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['especialidades', 'ausente_ate']);
        });
    }
};
