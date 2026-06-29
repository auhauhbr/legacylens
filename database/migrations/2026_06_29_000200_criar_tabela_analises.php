<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projeto_id')->constrained('projetos')->cascadeOnDelete();
            $table->string('status', 30)->default('pendente');
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->unsignedTinyInteger('pontuacao')->nullable();
            $table->string('nivel_risco', 30)->nullable();
            $table->text('resumo')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->json('configuracao_analise')->nullable();
            $table->json('versoes_analisadores')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['projeto_id', 'criado_em']);
            $table->index(['status', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analises');
    }
};
