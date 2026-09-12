<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CEP do contato — alimenta o parâmetro `zp` do Advanced Matching da CAPI
     * (hasheado, só os 5 primeiros dígitos). Opcional.
     */
    public function up(): void
    {
        Schema::table('contatos', function (Blueprint $table) {
            $table->string('cep', 9)->nullable()->after('cidade');
        });
    }

    public function down(): void
    {
        Schema::table('contatos', function (Blueprint $table) {
            $table->dropColumn('cep');
        });
    }
};
