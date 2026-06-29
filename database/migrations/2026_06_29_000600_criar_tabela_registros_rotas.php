<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_rotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->string('metodo', 50)->nullable();
            $table->text('uri');
            $table->string('nome')->nullable();
            $table->text('controlador')->nullable();
            $table->text('acao')->nullable();
            $table->json('middlewares')->nullable();
            $table->text('arquivo_origem')->nullable();
            $table->json('metadados')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['analise_id', 'metodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_rotas');
    }
};
