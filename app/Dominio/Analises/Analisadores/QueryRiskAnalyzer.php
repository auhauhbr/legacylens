<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\SeveridadeAchado;

class QueryRiskAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_ARQUIVOS = 5_000;

    private const LIMITE_EVIDENCIA = 240;

    /** @var list<string> */
    private const METODOS_RAW = [
        'selectraw',
        'whereraw',
        'orwhereraw',
        'havingraw',
        'orderbyraw',
        'groupbyraw',
    ];

    /** @var list<string> */
    private const METODOS_DIRETOS_DB = ['statement', 'unprepared', 'select'];

    /** @return list<DadosAchado> */
    public function analisar(string $diretorioProjeto): array
    {
        $achados = [];
        $duplicatas = [];

        foreach ($this->localizarArquivosPhp($diretorioProjeto) as $caminhoRelativo => $caminhoAbsoluto) {
            $conteudo = $this->lerArquivoLimitado($caminhoAbsoluto);

            if ($conteudo === null) {
                continue;
            }

            foreach ($this->analisarArquivo($caminhoRelativo, $conteudo) as $achado) {
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
    private function analisarArquivo(string $caminho, string $conteudo): array
    {
        $tokens = $this->tokensSignificativos($conteudo);
        $variaveisPdo = $this->identificarVariaveisPdo($tokens);
        $achados = [];

        foreach ($tokens as $indice => $token) {
            if ($token['id'] !== T_STRING || ($tokens[$indice + 1]['texto'] ?? null) !== '(') {
                continue;
            }

            $chamada = $this->classificarChamada($tokens, $indice, $variaveisPdo);

            if ($chamada === null) {
                continue;
            }

            $fim = $this->localizarFechamento($tokens, $indice + 1);

            if ($fim === null) {
                continue;
            }

            $argumento = $this->primeiroArgumento($tokens, $indice + 2, $fim);
            $linha = $token['linha'];
            $trecho = $this->trechoSanitizado($conteudo, $linha, $this->ultimaLinha($argumento, $linha));
            $evidencia = [
                'api' => $chamada['api'],
                'linha' => $linha,
                'trecho' => $trecho,
            ];

            $achados[] = $this->achadoBase($chamada, $caminho, $linha, $evidencia);

            if ($this->possuiConcatenacao($argumento)) {
                $achados[] = $this->achado(
                    'consultas.sql_raw_concatenacao',
                    SeveridadeAchado::Alta,
                    'SQL bruto com concatenação',
                    'A consulta bruta contém concatenação de variável e pode formar SQL de maneira insegura.',
                    'Substituir a concatenação por parâmetros vinculados e validar a origem dos valores.',
                    $caminho,
                    $linha,
                    $evidencia + ['indicador' => 'concatenacao'],
                );
            }

            if ($this->possuiInterpolacao($argumento)) {
                $achados[] = $this->achado(
                    'consultas.sql_raw_interpolacao',
                    SeveridadeAchado::Alta,
                    'SQL bruto com interpolação',
                    'A consulta bruta contém interpolação de variável e exige revisão quanto à parametrização.',
                    'Usar parâmetros vinculados em vez de interpolar valores diretamente no SQL.',
                    $caminho,
                    $linha,
                    $evidencia + ['indicador' => 'interpolacao'],
                );
            }

            if ($this->possuiEntradaUsuario($tokens, $argumento, $indice, $fim, $linha)) {
                $achados[] = $this->achado(
                    'consultas.possivel_entrada_usuario',
                    SeveridadeAchado::Alta,
                    'SQL bruto próximo de entrada do usuário',
                    'A consulta bruta está associada ou próxima de uma fonte de entrada do usuário.',
                    'Rastrear o valor até a origem, validar seu formato e usar parâmetros vinculados.',
                    $caminho,
                    $linha,
                    $evidencia + ['indicador' => 'entrada_usuario'],
                    NivelConfianca::Media,
                );
            }
        }

        return $achados;
    }

    /**
     * @param  list<array{id: int|null, texto: string, linha: int}>  $tokens
     * @param  array<string, true>  $variaveisPdo
     * @return array{tipo: string, api: string}|null
     */
    private function classificarChamada(array $tokens, int $indice, array $variaveisPdo): ?array
    {
        $nome = strtolower($tokens[$indice]['texto']);
        $operador = $tokens[$indice - 1]['texto'] ?? null;
        $receptor = strtolower($tokens[$indice - 2]['texto'] ?? '');

        if ($operador === '::' && $receptor === 'db' && $nome === 'raw') {
            return ['tipo' => 'raw', 'api' => 'DB::raw'];
        }

        if (($operador === '->' || $operador === '?->') && in_array($nome, self::METODOS_RAW, true)) {
            return ['tipo' => 'raw', 'api' => $tokens[$indice]['texto']];
        }

        if ($operador === '::' && $receptor === 'db' && in_array($nome, self::METODOS_DIRETOS_DB, true)) {
            return ['tipo' => 'direta', 'api' => 'DB::'.$tokens[$indice]['texto']];
        }

        if ($nome === 'mysqli_query' && ! in_array($operador, ['::', '->', '?->'], true)) {
            return ['tipo' => 'nativa', 'api' => 'mysqli_query'];
        }

        if ($operador === '::' && $receptor === 'pdo' && $nome === 'query') {
            return ['tipo' => 'nativa', 'api' => 'PDO::query'];
        }

        if (($operador === '->' || $operador === '?->') && $nome === 'query') {
            $variavel = $tokens[$indice - 2]['texto'] ?? '';
            $fim = $this->localizarFechamento($tokens, $indice + 1);
            $argumento = $fim === null ? [] : $this->primeiroArgumento($tokens, $indice + 2, $fim);

            if (isset($variaveisPdo[$variavel]) || $this->argumentoPareceSql($argumento)) {
                return ['tipo' => 'nativa', 'api' => $variavel.'->query'];
            }
        }

        return null;
    }

    /** @param array{tipo: string, api: string} $chamada */
    private function achadoBase(array $chamada, string $caminho, int $linha, array $evidencia): DadosAchado
    {
        return match ($chamada['tipo']) {
            'raw' => $this->achado(
                'consultas.sql_raw',
                SeveridadeAchado::Baixa,
                'Uso de SQL raw',
                'Foi detectado o uso de uma API de SQL raw, que reduz garantias oferecidas pelo query builder.',
                'Revisar a necessidade do SQL raw e preferir APIs parametrizadas do query builder quando possível.',
                $caminho,
                $linha,
                $evidencia,
            ),
            'direta' => $this->achado(
                'consultas.execucao_direta',
                SeveridadeAchado::Media,
                'Execução direta de SQL',
                'Foi detectada uma chamada direta de execução ou seleção SQL pela facade DB.',
                'Confirmar a parametrização, restringir entradas e documentar por que a consulta direta é necessária.',
                $caminho,
                $linha,
                $evidencia,
            ),
            default => $this->achado(
                'consultas.consulta_raw_revisao',
                SeveridadeAchado::Media,
                'Consulta raw exige revisão humana',
                'Foi detectada uma API nativa ou heurística de execução direta de consulta SQL.',
                'Revisar a origem da consulta e dos parâmetros, preferindo prepared statements.',
                $caminho,
                $linha,
                $evidencia,
                NivelConfianca::Media,
            ),
        };
    }

    private function achado(
        string $codigo,
        SeveridadeAchado $severidade,
        string $titulo,
        string $descricao,
        string $recomendacao,
        string $caminho,
        int $linha,
        array $evidencia,
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
            evidencia: $evidencia,
            metadados: ['analisador' => 'riscos_consultas', 'versao' => self::VERSAO],
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

    /**
     * @param  list<array{id: int|null, texto: string, linha: int}>  $tokens
     * @return array<string, true>
     */
    private function identificarVariaveisPdo(array $tokens): array
    {
        $variaveis = [];

        foreach ($tokens as $indice => $token) {
            if (
                $token['id'] === T_VARIABLE
                && ($tokens[$indice + 1]['texto'] ?? null) === '='
                && ($tokens[$indice + 2]['id'] ?? null) === T_NEW
                && strtolower($tokens[$indice + 3]['texto'] ?? '') === 'pdo'
            ) {
                $variaveis[$token['texto']] = true;
            }
        }

        return $variaveis;
    }

    /**
     * @param  list<array{id: int|null, texto: string, linha: int}>  $tokens
     * @return list<array{id: int|null, texto: string, linha: int}>
     */
    private function primeiroArgumento(array $tokens, int $inicio, int $fim): array
    {
        $argumento = [];
        $profundidade = 0;

        for ($indice = $inicio; $indice < $fim; $indice++) {
            $texto = $tokens[$indice]['texto'];

            if ($texto === ',' && $profundidade === 0) {
                break;
            }

            if (in_array($texto, ['(', '[', '{'], true)) {
                $profundidade++;
            } elseif (in_array($texto, [')', ']', '}'], true)) {
                $profundidade--;
            }

            $argumento[] = $tokens[$indice];
        }

        return $argumento;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function localizarFechamento(array $tokens, int $indiceAbertura): ?int
    {
        $profundidade = 0;

        for ($indice = $indiceAbertura, $total = count($tokens); $indice < $total; $indice++) {
            if ($tokens[$indice]['texto'] === '(') {
                $profundidade++;
            } elseif ($tokens[$indice]['texto'] === ')') {
                $profundidade--;

                if ($profundidade === 0) {
                    return $indice;
                }
            }
        }

        return null;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $argumento */
    private function possuiConcatenacao(array $argumento): bool
    {
        return $this->possuiToken($argumento, '.')
            && $this->possuiId($argumento, T_VARIABLE);
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $argumento */
    private function possuiInterpolacao(array $argumento): bool
    {
        return $this->possuiId($argumento, T_VARIABLE)
            && ($this->possuiToken($argumento, '"') || $this->possuiId($argumento, T_ENCAPSED_AND_WHITESPACE));
    }

    /**
     * @param  list<array{id: int|null, texto: string, linha: int}>  $tokens
     * @param  list<array{id: int|null, texto: string, linha: int}>  $argumento
     */
    private function possuiEntradaUsuario(array $tokens, array $argumento, int $inicioChamada, int $fimChamada, int $linha): bool
    {
        if ($this->contemFonteEntrada($argumento)) {
            return true;
        }

        foreach ($tokens as $indice => $token) {
            if ($indice >= $inicioChamada && $indice <= $fimChamada) {
                continue;
            }

            if (abs($token['linha'] - $linha) > 3) {
                continue;
            }

            if ($this->tokenEhFonteEntrada($tokens, $indice)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function contemFonteEntrada(array $tokens): bool
    {
        foreach ($tokens as $indice => $token) {
            if ($this->tokenEhFonteEntrada($tokens, $indice)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function tokenEhFonteEntrada(array $tokens, int $indice): bool
    {
        $token = $tokens[$indice];

        if ($token['id'] === T_VARIABLE && in_array($token['texto'], ['$_GET', '$_POST', '$_REQUEST'], true)) {
            return true;
        }

        if ($token['id'] !== T_STRING || ($tokens[$indice + 1]['texto'] ?? null) !== '(') {
            return false;
        }

        $nome = strtolower($token['texto']);

        if (in_array($nome, ['request', 'input'], true)) {
            return true;
        }

        if ($nome !== 'query') {
            return false;
        }

        $receptor = strtolower($tokens[$indice - 2]['texto'] ?? '');

        return $receptor === '$request'
            || ($tokens[$indice - 2]['texto'] ?? null) === ')'
            || str_contains($receptor, 'request');
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $argumento */
    private function argumentoPareceSql(array $argumento): bool
    {
        $texto = strtolower(implode('', array_column($argumento, 'texto')));

        return preg_match('/\\b(select|insert|update|delete|replace|with|pragma|show)\\b/', $texto) === 1;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function possuiToken(array $tokens, string $texto): bool
    {
        foreach ($tokens as $token) {
            if ($token['texto'] === $texto) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function possuiId(array $tokens, int $id): bool
    {
        foreach ($tokens as $token) {
            if ($token['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{id: int|null, texto: string, linha: int}> $tokens */
    private function ultimaLinha(array $tokens, int $padrao): int
    {
        if ($tokens === []) {
            return $padrao;
        }

        return min($padrao + 2, max(array_column($tokens, 'linha')));
    }

    private function trechoSanitizado(string $conteudo, int $linhaInicial, int $linhaFinal): string
    {
        $linhas = preg_split('/\\R/', $conteudo) ?: [];
        $trecho = implode(' ', array_slice($linhas, max(0, $linhaInicial - 1), max(1, $linhaFinal - $linhaInicial + 1)));
        $trecho = preg_replace('/\\s+/u', ' ', trim($trecho)) ?? '';

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

            if (
                $real !== false
                && str_starts_with($real, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
                && str_ends_with(strtolower($item), '.php')
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
