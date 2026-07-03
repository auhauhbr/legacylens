<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Dominio\Analises\DTO\ResultadoAnaliseArquivos;
use App\Enums\CategoriaAchado;
use App\Enums\SeveridadeAchado;

class FileHotspotAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_ARQUIVO_GRANDE = 500;

    private const LIMITE_CONTROLLER_GRANDE = 300;

    private const LIMITE_COMPLEXIDADE_ELEVADA = 25;

    private const LIMITE_ARQUIVO_SENSIVEL = 400;

    /** @var list<string> */
    private const TIPOS_SENSIVEIS = ['controller', 'model', 'service', 'command'];

    public function analisar(string $diretorioProjeto): ResultadoAnaliseArquivos
    {
        $metricas = [];
        $achados = [];

        foreach ($this->localizarArquivosPhp($diretorioProjeto) as $caminhoRelativo => $caminhoAbsoluto) {
            $conteudo = file_get_contents($caminhoAbsoluto);

            if ($conteudo === false) {
                continue;
            }

            $tipo = $this->identificarTipo($caminhoRelativo);
            $totalLinhas = $this->contarLinhas($conteudo);
            $complexidade = $this->calcularComplexidade($conteudo);
            $metricas[] = [
                'caminho_arquivo' => $caminhoRelativo,
                'tipo_arquivo' => $tipo,
                'total_linhas' => $totalLinhas,
                'complexidade_estimada' => $complexidade,
            ];
            $achados = [...$achados, ...$this->criarAchados($caminhoRelativo, $tipo, $totalLinhas, $complexidade)];
        }

        return new ResultadoAnaliseArquivos($achados, $metricas);
    }

    /** @return array<string, string> */
    private function localizarArquivosPhp(string $diretorioProjeto): array
    {
        $raiz = realpath($diretorioProjeto);

        if ($raiz === false || ! is_dir($raiz) || ! is_readable($raiz)) {
            return [];
        }

        $arquivos = [];
        $this->percorrerDiretorio($raiz, $raiz, '', $arquivos);
        ksort($arquivos);

        return $arquivos;
    }

    /** @param array<string, string> $arquivos */
    private function percorrerDiretorio(string $raiz, string $diretorio, string $relativo, array &$arquivos): void
    {
        $itens = scandir($diretorio);

        if ($itens === false) {
            return;
        }

        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $caminhoAbsoluto = $diretorio.DIRECTORY_SEPARATOR.$item;
            $caminhoRelativo = ltrim($relativo.'/'.$item, '/');

            if (is_link($caminhoAbsoluto)) {
                continue;
            }

            if (is_dir($caminhoAbsoluto)) {
                if (! $this->diretorioIgnorado($caminhoRelativo)) {
                    $this->percorrerDiretorio($raiz, $caminhoAbsoluto, $caminhoRelativo, $arquivos);
                }

                continue;
            }

            if (! is_file($caminhoAbsoluto) || ! is_readable($caminhoAbsoluto)) {
                continue;
            }

            $real = realpath($caminhoAbsoluto);

            if ($real === false || ! str_starts_with($real, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (str_ends_with(strtolower($item), '.php') && ! $this->arquivoIgnorado($caminhoRelativo)) {
                $arquivos[$caminhoRelativo] = $real;
            }
        }
    }

    private function diretorioIgnorado(string $caminho): bool
    {
        foreach (config('legacylens.ignored_directories', []) as $ignorado) {
            $ignorado = trim(str_replace('\\', '/', (string) $ignorado), '/');

            if ($ignorado !== '' && ($caminho === $ignorado || str_starts_with($caminho, $ignorado.'/'))) {
                return true;
            }
        }

        return false;
    }

    private function arquivoIgnorado(string $caminho): bool
    {
        foreach (config('legacylens.ignored_files', []) as $padrao) {
            $padrao = str_replace('\\', '/', (string) $padrao);

            if (fnmatch($padrao, $caminho) || fnmatch($padrao, basename($caminho))) {
                return true;
            }
        }

        return false;
    }

    private function identificarTipo(string $caminho): string
    {
        $caminho = str_replace('\\', '/', $caminho);

        return match (true) {
            str_contains($caminho, 'app/Http/Controllers/') || str_ends_with($caminho, 'Controller.php') => 'controller',
            str_contains($caminho, 'app/Models/') => 'model',
            str_contains($caminho, 'app/Services/'), str_contains($caminho, 'app/Servicos/') => 'service',
            str_contains($caminho, 'app/Console/Commands/') => 'command',
            str_contains($caminho, 'database/migrations/') => 'migration',
            str_contains($caminho, 'app/Http/Requests/') => 'request',
            str_contains($caminho, 'app/Policies/') => 'policy',
            str_contains($caminho, 'app/Providers/') => 'provider',
            default => 'outro',
        };
    }

    private function contarLinhas(string $conteudo): int
    {
        if ($conteudo === '') {
            return 0;
        }

        return substr_count($conteudo, "\n") + (str_ends_with($conteudo, "\n") ? 0 : 1);
    }

    private function calcularComplexidade(string $conteudo): int
    {
        $tokensContabilizados = [
            T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_CASE, T_CATCH,
            T_BOOLEAN_AND, T_BOOLEAN_OR, T_MATCH,
        ];
        $complexidade = 0;

        foreach (token_get_all($conteudo) as $token) {
            if ((is_array($token) && in_array($token[0], $tokensContabilizados, true)) || $token === '?') {
                $complexidade++;
            }
        }

        return $complexidade;
    }

    /** @return list<DadosAchado> */
    private function criarAchados(string $caminho, string $tipo, int $linhas, int $complexidade): array
    {
        $achados = [];

        if ($linhas >= self::LIMITE_ARQUIVO_GRANDE) {
            $achados[] = $this->achado(
                'arquivos.arquivo_grande', SeveridadeAchado::Media, 'Arquivo PHP grande',
                'O arquivo possui 500 ou mais linhas físicas e pode concentrar responsabilidades.',
                $caminho, ['total_linhas' => $linhas, 'limite' => self::LIMITE_ARQUIVO_GRANDE, 'tipo' => $tipo],
                'Avaliar a separação do arquivo em unidades menores e coesas.',
            );
        }

        if ($tipo === 'controller' && $linhas >= self::LIMITE_CONTROLLER_GRANDE) {
            $achados[] = $this->achado(
                'arquivos.controller_grande', SeveridadeAchado::Media, 'Controller grande',
                'O controller possui 300 ou mais linhas e pode conter regras além da coordenação HTTP.',
                $caminho, ['total_linhas' => $linhas, 'limite' => self::LIMITE_CONTROLLER_GRANDE],
                'Extrair regras de negócio para serviços ou ações testáveis.',
            );
        }

        if ($complexidade >= self::LIMITE_COMPLEXIDADE_ELEVADA) {
            $achados[] = $this->achado(
                'arquivos.complexidade_elevada', SeveridadeAchado::Media, 'Complexidade aproximada elevada',
                'A heurística encontrou 25 ou mais estruturas de decisão ou repetição no arquivo.',
                $caminho, ['complexidade_estimada' => $complexidade, 'limite' => self::LIMITE_COMPLEXIDADE_ELEVADA],
                'Revisar os fluxos condicionais e dividir comportamentos complexos em unidades menores.',
            );
        }

        if (in_array($tipo, self::TIPOS_SENSIVEIS, true) && $linhas >= self::LIMITE_ARQUIVO_SENSIVEL) {
            $achados[] = $this->achado(
                'arquivos.area_sensivel_muito_longa', SeveridadeAchado::Alta, 'Arquivo sensível muito longo',
                'Um arquivo de área sensível possui 400 ou mais linhas, elevando o custo de manutenção e revisão.',
                $caminho, ['total_linhas' => $linhas, 'limite' => self::LIMITE_ARQUIVO_SENSIVEL, 'tipo' => $tipo],
                'Priorizar a decomposição deste arquivo preservando testes de caracterização.',
            );
        }

        return $achados;
    }

    private function achado(
        string $codigo,
        SeveridadeAchado $severidade,
        string $titulo,
        string $descricao,
        string $caminho,
        array $evidencia,
        string $recomendacao,
    ): DadosAchado {
        return new DadosAchado(
            $codigo,
            CategoriaAchado::Arquitetura,
            $severidade,
            $titulo,
            $descricao,
            $recomendacao,
            $caminho,
            evidencia: $evidencia,
            metadados: ['analisador' => 'hotspots_arquivos', 'versao' => self::VERSAO],
        );
    }
}
