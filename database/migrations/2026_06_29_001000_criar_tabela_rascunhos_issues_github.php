<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rascunhos_issues_github', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->foreignId('achado_id')->nullable()->constrained('achados')->nullOnDelete();
            $table->foreignId('etapa_modernizacao_id')->nullable()->constrained('etapas_modernizacao')->nullOnDelete();
            $table->string('titulo');
            $table->longText('corpo');
            $table->json('rotulos')->nullable();
            $table->string('status', 20)->default('rascunho');
            $table->text('url_issue_github')->nullable();
            $table->timestamp('publicado_em')->nullable();
            $table->json('metadados')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['analise_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rascunhos_issues_github');
    }
};
