<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('tipo', 50)->default('laravel');
            $table->string('tipo_origem', 30)->default('local');
            $table->text('caminho_local')->nullable();
            $table->text('url_repositorio')->nullable();
            $table->string('ramo')->nullable();
            $table->json('configuracao_padrao_analise')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['usuario_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projetos');
    }
};
