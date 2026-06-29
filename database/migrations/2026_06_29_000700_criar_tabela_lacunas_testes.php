<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lacunas_testes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->string('tipo_alvo', 50);
            $table->string('nome_alvo');
            $table->text('caminho_alvo')->nullable();
            $table->text('caminho_teste_esperado')->nullable();
            $table->unsignedTinyInteger('confianca')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['analise_id', 'tipo_alvo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lacunas_testes');
    }
};
