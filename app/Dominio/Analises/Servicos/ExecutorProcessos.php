<?php

namespace App\Dominio\Analises\Servicos;

use App\Dominio\Analises\DTO\ResultadoProcesso;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class ExecutorProcessos
{
    public function __construct(
        private readonly ResolvedorCaminhoSeguro $resolvedorCaminho,
    ) {}

    public function executar(string $chaveComando, string $diretorioTrabalho): ResultadoProcesso
    {
        $permitidos = config('legacylens.allowed_commands', []);
        $definicoes = config('legacylens.command_definitions', []);

        if (! in_array($chaveComando, $permitidos, true) || ! isset($definicoes[$chaveComando])) {
            throw new InvalidArgumentException('A chave de comando informada não é permitida.');
        }

        $diretorioSeguro = $this->resolvedorCaminho->resolver($diretorioTrabalho);
        $inicio = hrtime(true);

        try {
            $resultado = Process::path($diretorioSeguro)
                ->timeout((int) config('legacylens.process_timeout_seconds', 30))
                ->run($definicoes[$chaveComando]);

            return new ResultadoProcesso(
                chaveComando: $chaveComando,
                codigoSaida: $resultado->exitCode(),
                sucesso: $resultado->successful(),
                saidaPadrao: $this->limitarSaida($resultado->output()),
                saidaErro: $this->limitarSaida($resultado->errorOutput()),
                tempoExcedido: false,
                duracaoMilissegundos: $this->duracaoEmMilissegundos($inicio),
            );
        } catch (ProcessTimedOutException $excecao) {
            return new ResultadoProcesso(
                chaveComando: $chaveComando,
                codigoSaida: $excecao->result->exitCode(),
                sucesso: false,
                saidaPadrao: $this->limitarSaida($excecao->result->output()),
                saidaErro: $this->limitarSaida($excecao->result->errorOutput()),
                tempoExcedido: true,
                duracaoMilissegundos: $this->duracaoEmMilissegundos($inicio),
            );
        }
    }

    private function limitarSaida(string $saida): string
    {
        return substr($saida, 0, (int) config('legacylens.max_process_output_bytes', 1_048_576));
    }

    private function duracaoEmMilissegundos(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
