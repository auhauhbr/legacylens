<?php

namespace App\Dominio\Analises\Servicos;

use App\Dominio\Analises\Analisadores\ComposerAnalyzer;
use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\CategoriaAchado;
use App\Enums\NivelRisco;
use App\Enums\SeveridadeAchado;
use App\Enums\StatusAnalise;
use App\Enums\TipoOrigemProjeto;
use App\Models\Analise;
use RuntimeException;
use Throwable;

class ExecutorAnalise
{
    public function __construct(
        private readonly ResolvedorCaminhoSeguro $resolvedorCaminho,
        private readonly FabricaAchados $fabricaAchados,
        private readonly ComposerAnalyzer $composerAnalyzer,
    ) {}

    public function executar(Analise $analise): Analise
    {
        $inicio = hrtime(true);
        $analise->forceFill([
            'status' => StatusAnalise::EmExecucao,
            'iniciado_em' => now(),
            'finalizado_em' => null,
            'duracao_segundos' => null,
            'mensagem_erro' => null,
        ])->save();

        try {
            $projeto = $analise->projeto()->first();

            if ($projeto === null) {
                throw new RuntimeException('O projeto associado à análise não foi encontrado.');
            }

            if ($projeto->tipo_origem !== TipoOrigemProjeto::Local) {
                throw new RuntimeException('O tipo de origem do projeto ainda não é suportado.');
            }

            if (blank($projeto->caminho_local)) {
                throw new RuntimeException('O projeto não possui um caminho local cadastrado.');
            }

            $diretorioProjeto = $this->resolvedorCaminho->resolver($projeto->caminho_local);
            $this->criarAchadosIniciais($analise);
            $this->executarComposerAnalyzer($analise, $diretorioProjeto);

            $pontuacao = $this->calcularPontuacaoTemporaria($analise);

            $analise->forceFill([
                'status' => StatusAnalise::Concluida,
                'finalizado_em' => now(),
                'duracao_segundos' => $this->duracaoEmSegundos($inicio),
                'pontuacao' => $pontuacao,
                'nivel_risco' => $this->nivelRisco($pontuacao),
                'resumo' => 'Análise passiva do Composer concluída.',
                'versoes_analisadores' => ['composer' => ComposerAnalyzer::VERSAO],
            ])->save();
        } catch (Throwable $excecao) {
            $analise->forceFill([
                'status' => StatusAnalise::Falhou,
                'finalizado_em' => now(),
                'duracao_segundos' => $this->duracaoEmSegundos($inicio),
                'mensagem_erro' => mb_substr($excecao->getMessage(), 0, 1000),
            ])->save();
        }

        return $analise->refresh();
    }

    private function criarAchadosIniciais(Analise $analise): void
    {
        $dados = [
            new DadosAchado(
                codigo: 'analise.iniciada',
                categoria: CategoriaAchado::Documentacao,
                severidade: SeveridadeAchado::Informativa,
                titulo: 'Análise iniciada',
                descricao: 'A infraestrutura de análise foi iniciada com sucesso.',
                evidencia: ['estado' => 'iniciada'],
                metadados: ['temporario' => true],
            ),
            new DadosAchado(
                codigo: 'projeto.caminho_validado',
                categoria: CategoriaAchado::Arquitetura,
                severidade: SeveridadeAchado::Informativa,
                titulo: 'Caminho do projeto validado',
                descricao: 'O diretório cadastrado existe e passou pelas validações de segurança.',
                evidencia: ['diretorio_valido' => true],
                metadados: ['temporario' => true],
            ),
        ];

        foreach ($dados as $achado) {
            $this->fabricaAchados->criar($analise, $achado);
        }
    }

    private function executarComposerAnalyzer(Analise $analise, string $diretorioProjeto): void
    {
        $resultado = $this->composerAnalyzer->analisar($diretorioProjeto);

        foreach ($resultado->achados as $achado) {
            $this->fabricaAchados->criar($analise, $achado);
        }

        foreach ($resultado->dependencias as $dependencia) {
            $analise->dependencias()->updateOrCreate([
                'nome_pacote' => $dependencia['nome_pacote'],
                'escopo' => $dependencia['escopo'],
            ], [
                'restricao' => $dependencia['restricao'],
                'versao_atual' => $dependencia['versao_atual'],
                'direta' => true,
                'desenvolvimento' => $dependencia['desenvolvimento'],
                'abandonada' => false,
                'possui_alerta_seguranca' => false,
                'metadados' => ['origem' => 'composer'],
            ]);
        }
    }

    private function calcularPontuacaoTemporaria(Analise $analise): int
    {
        $descontos = [
            SeveridadeAchado::Critica->value => 15,
            SeveridadeAchado::Alta->value => 8,
            SeveridadeAchado::Media->value => 4,
            SeveridadeAchado::Baixa->value => 1,
            SeveridadeAchado::Informativa->value => 0,
        ];

        $desconto = $analise->achados()
            ->get(['severidade'])
            ->sum(fn ($achado): int => $descontos[$achado->severidade->value] ?? 0);

        return max(0, 100 - $desconto);
    }

    private function nivelRisco(int $pontuacao): NivelRisco
    {
        return match (true) {
            $pontuacao >= 85 => NivelRisco::Saudavel,
            $pontuacao >= 70 => NivelRisco::AtencaoLeve,
            $pontuacao >= 50 => NivelRisco::Moderado,
            $pontuacao >= 30 => NivelRisco::Alta,
            default => NivelRisco::Critica,
        };
    }

    private function duracaoEmSegundos(int $inicio): int
    {
        return max(0, (int) ceil((hrtime(true) - $inicio) / 1_000_000_000));
    }
}
