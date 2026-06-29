<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\CategoriaAchado;
use App\Enums\SeveridadeAchado;

class DocumentationAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_README = 1_048_576;

    /** @return list<DadosAchado> */
    public function analisar(string $diretorioProjeto): array
    {
        $readme = $this->localizarReadme($diretorioProjeto);
        $achados = [];

        if ($readme === null) {
            $achados[] = $this->achado(
                'documentacao.readme_ausente',
                SeveridadeAchado::Baixa,
                'README ausente',
                'Não foi encontrado um arquivo README.md, README.rst ou README.txt na raiz do projeto.',
                ['arquivos_procurados' => ['README.md', 'README.rst', 'README.txt'], 'estado' => 'ausente'],
                'Adicionar um README com instruções essenciais para desenvolvimento e operação.',
            );
        } else {
            $caminhoRelativo = basename($readme);
            $conteudo = $this->lerArquivoLimitado($readme);
            $achados[] = $this->achado(
                'documentacao.readme_detectado',
                SeveridadeAchado::Informativa,
                'README detectado',
                'Foi encontrado um arquivo de documentação principal na raiz do projeto.',
                ['arquivo' => $caminhoRelativo, 'estado' => 'presente'],
                caminhoArquivo: $caminhoRelativo,
            );

            if ($conteudo === null) {
                $achados[] = $this->achado(
                    'documentacao.readme_nao_analisavel',
                    SeveridadeAchado::Baixa,
                    'README não analisável',
                    'O README existe, mas não pôde ser analisado com segurança por estar ilegível ou exceder o limite de tamanho.',
                    ['arquivo' => $caminhoRelativo, 'limite_bytes' => self::LIMITE_README],
                    'Manter o README legível e com tamanho adequado para análise.',
                    $caminhoRelativo,
                );
            } else {
                $achados = [...$achados, ...$this->avaliarSecoesReadme($conteudo, $caminhoRelativo)];
            }
        }

        $achados[] = $this->achadoAmbiente($diretorioProjeto);
        $achados[] = $this->achadoDocumentacaoPublica($diretorioProjeto);

        return $achados;
    }

    /** @return list<DadosAchado> */
    private function avaliarSecoesReadme(string $conteudo, string $caminho): array
    {
        $sinais = [
            'instalacao' => '~\b(instala(?:ção|cao|r)|installation|setup|composer\s+install)\b~iu',
            'ambiente' => '~(?:\.env(?:\.example)?|vari[aá]ve(?:l|is)\s+de\s+ambiente|environment\s+variables?|configuration|configura(?:ção|cao))~iu',
            'testes' => '~\b(testes?|tests?|phpunit|pest|artisan\s+test)\b~iu',
            'desenvolvimento' => '~\b(desenvolvimento|development|local\s+development|artisan\s+serve|npm\s+run\s+dev|composer\s+run\s+dev)\b~iu',
            'deploy' => '~\b(deploy(?:ment)?|produção|producao|production|release)\b~iu',
        ];
        $detectados = [];

        foreach ($sinais as $nome => $padrao) {
            $detectados[$nome] = preg_match($padrao, $conteudo) === 1;
        }

        $achados = [
            $this->achado(
                'documentacao.readme_cobertura',
                SeveridadeAchado::Informativa,
                'Cobertura do README avaliada',
                'O conteúdo do README foi avaliado quanto às instruções operacionais essenciais.',
                ['arquivo' => $caminho, 'sinais' => $detectados],
                caminhoArquivo: $caminho,
            ),
        ];

        if (! $detectados['testes']) {
            $achados[] = $this->achado(
                'documentacao.instrucoes_testes_ausentes',
                SeveridadeAchado::Media,
                'Instruções de testes ausentes no README',
                'Não foram encontrados sinais de como executar os testes do projeto.',
                ['arquivo' => $caminho, 'sinal' => 'testes', 'detectado' => false],
                'Documentar o comando e os pré-requisitos para executar a suíte de testes.',
                $caminho,
            );
        }

        $lacunas = array_keys(array_filter($detectados, fn (bool $detectado): bool => ! $detectado));

        if (array_diff($lacunas, ['testes']) !== []) {
            $achados[] = $this->achado(
                'documentacao.readme_incompleto',
                SeveridadeAchado::Baixa,
                'README com instruções incompletas',
                'O README não apresenta todos os sinais esperados para instalação, configuração, desenvolvimento e operação.',
                ['arquivo' => $caminho, 'itens_ausentes' => array_values($lacunas)],
                'Complementar o README com as instruções operacionais ausentes.',
                $caminho,
            );
        }

        return $achados;
    }

    private function achadoAmbiente(string $diretorioProjeto): DadosAchado
    {
        $presente = $this->arquivoSeguro($diretorioProjeto.DIRECTORY_SEPARATOR.'.env.example', $diretorioProjeto);

        return $this->achado(
            $presente ? 'documentacao.env_exemplo_detectado' : 'documentacao.env_exemplo_ausente',
            $presente ? SeveridadeAchado::Informativa : SeveridadeAchado::Media,
            $presente ? '.env.example detectado' : '.env.example ausente',
            $presente
                ? 'Foi encontrado um arquivo de exemplo para configuração do ambiente.'
                : 'Não foi encontrado um .env.example para orientar a configuração sem expor segredos reais.',
            ['arquivo' => '.env.example', 'estado' => $presente ? 'presente' : 'ausente'],
            $presente ? null : 'Adicionar um .env.example sem credenciais ou segredos reais.',
            '.env.example',
        );
    }

    private function achadoDocumentacaoPublica(string $diretorioProjeto): DadosAchado
    {
        $candidatos = ['docs', 'doc', 'documentation', 'public/docs'];
        $detectado = null;

        foreach ($candidatos as $candidato) {
            $caminho = $diretorioProjeto.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $candidato);

            if ($this->diretorioSeguro($caminho, $diretorioProjeto)) {
                $detectado = $candidato;
                break;
            }
        }

        return $this->achado(
            $detectado ? 'documentacao.publica_detectada' : 'documentacao.publica_ausente',
            $detectado ? SeveridadeAchado::Informativa : SeveridadeAchado::Baixa,
            $detectado ? 'Documentação pública detectada' : 'Documentação pública não detectada',
            $detectado
                ? 'Foi encontrado um diretório dedicado à documentação complementar do projeto.'
                : 'Não foi encontrado um diretório convencional de documentação complementar.',
            ['diretorios_procurados' => $candidatos, 'diretorio_detectado' => $detectado],
            $detectado ? null : 'Considerar uma pasta docs/ para documentação técnica detalhada.',
            $detectado,
        );
    }

    private function localizarReadme(string $diretorioProjeto): ?string
    {
        foreach (['README.md', 'README.rst', 'README.txt'] as $nome) {
            $caminho = $diretorioProjeto.DIRECTORY_SEPARATOR.$nome;

            if ($this->arquivoSeguro($caminho, $diretorioProjeto)) {
                return $caminho;
            }
        }

        return null;
    }

    private function lerArquivoLimitado(string $caminho): ?string
    {
        $tamanho = filesize($caminho);

        if ($tamanho === false || $tamanho > self::LIMITE_README) {
            return null;
        }

        $conteudo = file_get_contents($caminho);

        return $conteudo === false ? null : $conteudo;
    }

    private function arquivoSeguro(string $caminho, string $raiz): bool
    {
        return $this->caminhoSeguro($caminho, $raiz, 'is_file');
    }

    private function diretorioSeguro(string $caminho, string $raiz): bool
    {
        return $this->caminhoSeguro($caminho, $raiz, 'is_dir');
    }

    private function caminhoSeguro(string $caminho, string $raiz, string $verificador): bool
    {
        if (! $verificador($caminho) || ! is_readable($caminho) || is_link($caminho)) {
            return false;
        }

        $real = realpath($caminho);
        $raizReal = realpath($raiz);

        return $real !== false && $raizReal !== false
            && str_starts_with($real, rtrim($raizReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function achado(
        string $codigo,
        SeveridadeAchado $severidade,
        string $titulo,
        string $descricao,
        array $evidencia,
        ?string $recomendacao = null,
        ?string $caminhoArquivo = null,
    ): DadosAchado {
        return new DadosAchado(
            $codigo,
            CategoriaAchado::Documentacao,
            $severidade,
            $titulo,
            $descricao,
            $recomendacao,
            $caminhoArquivo,
            evidencia: $evidencia,
            metadados: ['analisador' => 'documentacao', 'versao' => self::VERSAO],
        );
    }
}
