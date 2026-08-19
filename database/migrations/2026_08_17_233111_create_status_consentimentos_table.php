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
        Schema::create('status_consentimentos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome')->unique();
            $table->string('cor_fundo', 9);
            $table->string('cor_texto', 9);
            $table->boolean('visivel_cadastro')->default(false)->index();
            $table->unsignedSmallInteger('ordem')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_consentimentos');
    }
};
