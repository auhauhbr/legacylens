<?php

namespace App\Jobs;

use App\Dominio\Analises\Servicos\ExecutorAnalise;
use App\Enums\StatusAnalise;
use App\Models\Analise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecutarAnaliseProjeto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $analiseId,
    ) {}

    public function handle(ExecutorAnalise $executor): void
    {
        $analise = Analise::query()->findOrFail($this->analiseId);

        $executor->executar($analise);
    }

    public function failed(?Throwable $excecao): void
    {
        Analise::query()->whereKey($this->analiseId)->update([
            'status' => StatusAnalise::Falhou->value,
            'finalizado_em' => now(),
            'mensagem_erro' => 'Falha inesperada na execução em fila.',
        ]);
    }
}
