<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\SeveridadeAchado;

class DebugCodeAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_ARQUIVOS = 5_000;

    private const LIMITE_EVIDENCIA = 240;

    /** @var array<string, array{codigo: string, severidade: SeveridadeAchado, titulo: string}> */
    private const CHAMADAS_PHP = [
        'dd' => [
            'codigo' => 'debug.interrupcao_execucao',
            'severidade' => SeveridadeAchado::Alta,
            'titulo' => 'Chamada de debug interrompe a execução',
        ],
        'die' => [
            'codigo' => 'debug.interrupcao_execucao',
            'severidade' => SeveridadeAchado::Alta,
            'titulo' => 'Interrupção explícita de execução',
        ],
        'exit' => [
            'codigo' => 'debug.interrupcao_execucao',
            'severidade' => SeveridadeAchado::Alta,
            'titulo' => 'Interrupção explícita de execução',
        ],
        'dump' => [
            'codigo' => 'debug.possivel_vazamento_informacao',
            'severidade' => SeveridadeAchado::Media,
            'titulo' => 'Saída de debug encontrada',
        ],
        'var_dump' => [
            'codigo' => 'debug.possivel_vazamento_informacao',
            'severidade' => SeveridadeAchado::Media,
            'titulo' => 'Saída de debug encontrada',
        ],
        'print_r' => [
            'codigo' => 'debug.possivel_vazamento_informacao',
            'severidade' => SeveridadeAchado::Media,
            'titulo' => 'Saída de debug encontrada',
        ],
        'ray' => [
            'codigo' => 'debug.possivel_vazamento_informacao',
            'severidade' => SeveridadeAchado::Media,
            'titulo' => 'Chamada de ferramenta de debug encontrada',
        ],
    ];

    /** @return list<DadosAchado> */
    public function analisar(string $diretorioProjeto): array
    {
        $achados = [];
        $duplicatas = [];

        foreach ($this->localizarArquivos($diretorioProjeto) as $caminhoRelativo => $caminhoAbsoluto) {
            $conteudo = $this->lerArquivoLimitado($caminhoAbsoluto);

            if ($conteudo === null) {
                continue;
            }

            $achadosArquivo = $this->analisarPhp($caminhoRelativo, $conteudo);

            if (str_ends_with(strtolower($caminhoRelativo), '.blade.php')) {
                $achadosArquivo = [
                    ...$achadosArquivo,
                    ...$this->analisarBlade($caminhoRelativo, $conteudo),
                ];
            }

            foreach ($achadosArquivo as $achado) {
                $chave = implode('|', [
                    $achado->codigo,
                    $achado->caminhoArquivo,
                    $achado->linhaInicial,
                ]);

                if (isset($duplicatas[$chave])) {
                    continue;
                }

                $duplicatas[$chave] = true;
                $achados[] = $achado;
            }
        }

        return $achados;
    }

    /** @return list<DadosAchado> */
    private function analisarPhp(string $caminho, string $conteudo): array
    {
        $tokens = $this->tokensSignificativos($conteudo);
        $achados = [];

        foreach ($tokens as $indice => $token) {
            $chamada = null;

            if ($token['id'] === T_EXIT) {
                $chamada = strtolower(trim($token['texto']));
            } elseif ($token['id'] === T_STRING && ($tokens[$indice + 1]['texto'] ?? null) === '(') {
                $candidata = strtolower($token['texto']);
                $anterior = $tokens[$indice - 1] ?? null;

                if (
                    isset(self::CHAMADAS_PHP[$candidata])
                    && ! in_array($anterior['texto'] ?? null, ['->', '?->', '::'], true)
                    && ($anterior['id'] ?? null) !== T_FUNCTION
                    && ($anterior['id'] ?? null) !== T_FN
                ) {
                    $chamada = $candidata;
                }
            }

            if ($chamada === null || ! isset(self::CHAMADAS_PHP[$chamada])) {
                continue;
            }

            $configuracao = self::CHAMADAS_PHP[$chamada];
            $achados[] = $this->achadoPhp(
                $configuracao['codigo'],
                $configuracao['severidade'],
                $configuracao['titulo'],
                $chamada,
                $caminho,
                $token['linha'],
                $conteudo,
            );
        }

        return $achados;
    }

    /** @return list<DadosAchado> */
    private function analisarBlade(string $caminho, string $conteudo): array
    {
        $conteudoAnalisavel = $this->mascararComentariosBlade($conteudo);
        $conteudoAnalisavel = $this->mascararComentariosPhp($conteudoAnalisavel);
        $achados = [];

        preg_match_all(
            '/(?<!@)@(dd|dump)\s*(?=\()/i',
            $conteudoAnalisavel,
            $ocorrencias,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($ocorrencias[1] ?? [] as [$diretiva, $deslocamento]) {
            $linha = substr_count(substr($conteudoAnalisavel, 0, $deslocamento), "\n") + 1;
            $nome = strtolower($diretiva);
            $interrompe = $nome === 'dd';

            $achados[] = $this->achado(
                codigo: 'debug.template_blade',
                severidade: $interrompe ? SeveridadeAchado::Alta : SeveridadeAchado::Media,
                titulo: $interrompe ? 'Debug interrompe template Blade' : 'Saída de debug em template Blade',
                descricao: $interrompe
                    ? 'A diretiva @dd pode interromper a renderização do template e exige revisão humana.'
                    : 'A diretiva @dump pode expor dados ou poluir a saída renderizada e exige revisão humana.',
                recomendacao: 'Remover a diretiva de debug ou substituí-la por observabilidade controlada antes da publicação.',
                chamada: '@'.$nome,
                caminho: $caminho,
                linha: $linha,
                conteudo: $conteudo,
                confianca: NivelConfianca::Alta,
            );
        }

        return $achados;
    }

    private function achadoPhp(
        string $codigo,
        SeveridadeAchado $severidade,
        string $titulo,
        string $chamada,
        string $caminho,
        int $linha,
        string $conteudo,
    ): DadosAchado {
        $interrompe = in_array($chamada, ['dd', 'die', 'exit'], true);

        return $this->achado(
            codigo: $codigo,
            severidade: $severidade,
            titulo: $titulo,
            descricao: $interrompe
                ? 'Foi encontrada uma chamada que pode interromper o fluxo normal da aplicação e exige revisão humana.'
                : 'Foi encontrada uma chamada de debug que pode expor informações ou poluir a saída da aplicação.',
            recomendacao: $interrompe
                ? 'Remover a interrupção de debug ou substituí-la por tratamento de erro e observabilidade controlada.'
                : 'Remover a saída de debug ou substituí-la por logging estruturado sem dados sensíveis.',
            chamada: $chamada,
            caminho: $caminho,
            linha: $linha,
            conteudo: $conteudo,
        );
    }

    private function achado(
        string $codigo,
        SeveridadeAchado $severidade,
        string $titulo,
        string $descricao,
        string $recomendacao,
        string $chamada,
        string $caminho,
        int $linha,
        string $conteudo,
        NivelConfianca $confianca = NivelConfianca::Alta,
    ): DadosAchado {
        return new DadosAchado(
            codigo: $codigo,
            categoria: CategoriaAchado::Seguranca,
            severidade: $severidade,
            titulo: $titulo,
            descricao: $descricao,
            recomendacao: $recomendacao,
            caminhoArquivo: $caminho,
            linhaInicial: $linha,
            linhaFinal: $linha,
            evidencia: [
                'chamada' => $chamada,
                'linha' => $linha,
                'trecho' => $this->trechoSanitizado($conteudo, $linha),
            ],
            metadados: ['analisador' => 'codigo_debug', 'versao' => self::VERSAO],
            confianca: $confianca,
        );
    }

    /**
     * @return list<array{id: int|null, texto: string, linha: int}>
     */
    private function tokensSignificativos(string $conteudo): array
    {
        $resultado = [];
        $linhaAtual = 1;

        foreach (token_get_all($conteudo) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $texto = is_array($token) ? $token[1] : $token;
            $linha = is_array($token) ? $token[2] : $linhaAtual;
            $linhaAtual = $linha + substr_count($texto, "\n");

            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)) {
                continue;
            }

            $resultado[] = ['id' => $id, 'texto' => $texto, 'linha' => $linha];
        }

        return $resultado;
    }

    private function mascararComentariosBlade(string $conteudo): string
    {
        $mascarado = preg_replace_callback(
            '/\{\{--.*?--\}\}/s',
            fn (array $ocorrencia): string => $this->preservarQuebras($ocorrencia[0]),
            $conteudo,
        ) ?? $conteudo;

        return preg_replace_callback(
            '/<!--.*?-->/s',
            fn (array $ocorrencia): string => $this->preservarQuebras($ocorrencia[0]),
            $mascarado,
        ) ?? $mascarado;
    }

    private function mascararComentariosPhp(string $conteudo): string
    {
        $resultado = '';

        foreach (token_get_all($conteudo) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $resultado .= $this->preservarQuebras($token[1]);

                continue;
            }

            $resultado .= is_array($token) ? $token[1] : $token;
        }

        return $resultado;
    }

    private function preservarQuebras(string $conteudo): string
    {
        return preg_replace('/[^\r\n]/', ' ', $conteudo) ?? '';
    }

    private function trechoSanitizado(string $conteudo, int $linha): string
    {
        $linhas = preg_split('/\R/', $conteudo) ?: [];
        $trecho = $linhas[max(0, $linha - 1)] ?? '';
        $trecho = preg_replace('/\s+/u', ' ', trim($trecho)) ?? '';

        return mb_substr($trecho, 0, self::LIMITE_EVIDENCIA);
    }

    private function lerArquivoLimitado(string $caminho): ?string
    {
        $tamanho = filesize($caminho);
        $limite = max(1, (int) config('legacylens.max_file_size_mb', 2)) * 1_048_576;

        if ($tamanho === false || $tamanho > $limite) {
            return null;
        }

        $conteudo = file_get_contents($caminho);

        return $conteudo === false ? null : $conteudo;
    }

    /** @return array<string, string> */
    private function localizarArquivos(string $diretorioProjeto): array
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
        if (count($arquivos) >= self::LIMITE_ARQUIVOS) {
            return;
        }

        $itens = scandir($diretorio);

        if ($itens === false) {
            return;
        }

        foreach ($itens as $item) {
            if ($item === '.' || $item === '..' || count($arquivos) >= self::LIMITE_ARQUIVOS) {
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
            $nomeMinusculo = strtolower($item);

            if (
                $real !== false
                && str_starts_with($real, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                && (str_ends_with($nomeMinusculo, '.php') || str_ends_with($nomeMinusculo, '.blade.php'))
                && ! $this->arquivoIgnorado($caminhoRelativo)
            ) {
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
}
