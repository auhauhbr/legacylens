<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Dominio\Analises\DTO\ResultadoAnaliseComposer;
use App\Enums\CategoriaAchado;
use App\Enums\SeveridadeAchado;
use JsonException;

class ComposerAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_COMPOSER_JSON = 1_048_576;

    private const LIMITE_COMPOSER_LOCK = 10_485_760;

    public function analisar(string $diretorioProjeto): ResultadoAnaliseComposer
    {
        $caminhoManifesto = $diretorioProjeto.DIRECTORY_SEPARATOR.'composer.json';

        if (! $this->arquivoLegivelNaRaiz($caminhoManifesto, $diretorioProjeto)) {
            return new ResultadoAnaliseComposer([
                $this->achado(
                    'composer.manifesto_ausente',
                    CategoriaAchado::Dependencias,
                    SeveridadeAchado::Media,
                    'composer.json ausente',
                    'Não foi possível inventariar as dependências porque o composer.json não foi encontrado.',
                    ['arquivo' => 'composer.json', 'estado' => 'ausente'],
                    'Adicionar ou restaurar um composer.json válido na raiz do projeto.',
                    'composer.json',
                ),
            ], []);
        }

        $manifesto = $this->lerJson($caminhoManifesto, self::LIMITE_COMPOSER_JSON);

        if ($manifesto === null) {
            return new ResultadoAnaliseComposer([
                $this->achado(
                    'composer.manifesto_invalido',
                    CategoriaAchado::Dependencias,
                    SeveridadeAchado::Alta,
                    'composer.json inválido',
                    'O manifesto não pôde ser lido como um objeto JSON válido e a análise de dependências foi interrompida.',
                    ['arquivo' => 'composer.json', 'estado' => 'invalido_ou_muito_grande'],
                    'Corrigir e validar o composer.json antes de uma nova análise.',
                    'composer.json',
                ),
            ], []);
        }

        $requeridas = $this->normalizarDependencias($manifesto['require'] ?? null);
        $requeridasDesenvolvimento = $this->normalizarDependencias($manifesto['require-dev'] ?? null);
        $versoesInstaladas = $this->lerVersoesInstaladas($diretorioProjeto);
        $achados = $this->criarAchadosManifesto($requeridas, $requeridasDesenvolvimento);
        $dependencias = [];

        foreach ([['pacotes' => $requeridas, 'escopo' => 'require', 'desenvolvimento' => false], ['pacotes' => $requeridasDesenvolvimento, 'escopo' => 'require-dev', 'desenvolvimento' => true]] as $grupo) {
            foreach ($grupo['pacotes'] as $nome => $restricao) {
                if ($nome === 'php') {
                    continue;
                }

                $dependencias[] = [
                    'nome_pacote' => $nome,
                    'restricao' => $restricao,
                    'versao_atual' => $versoesInstaladas === null ? null : ($versoesInstaladas[$nome] ?? null),
                    'escopo' => $grupo['escopo'],
                    'desenvolvimento' => $grupo['desenvolvimento'],
                ];
            }
        }

        if ($versoesInstaladas === null) {
            $achados[] = $this->achado(
                'composer.lock_ausente',
                CategoriaAchado::Dependencias,
                SeveridadeAchado::Baixa,
                'composer.lock ausente',
                'As versões instaladas não puderam ser determinadas porque o composer.lock não está disponível ou não é válido.',
                ['arquivo' => 'composer.lock', 'estado' => 'ausente_ou_invalido'],
                'Versionar um composer.lock válido para tornar as instalações reprodutíveis.',
                'composer.lock',
            );
        }

        return new ResultadoAnaliseComposer($achados, $dependencias);
    }

    /** @return list<DadosAchado> */
    private function criarAchadosManifesto(array $requeridas, array $requeridasDesenvolvimento): array
    {
        $achados = [];

        if (isset($requeridas['php'])) {
            $achados[] = $this->achado(
                'composer.php_detectado', CategoriaAchado::Dependencias, SeveridadeAchado::Informativa,
                'Restrição de PHP detectada', 'O manifesto declara uma restrição explícita para o runtime PHP.',
                ['chave' => 'require.php', 'restricao' => $requeridas['php']], caminhoArquivo: 'composer.json',
            );
        }

        if (isset($requeridas['laravel/framework'])) {
            $achados[] = $this->achado(
                'composer.laravel_detectado', CategoriaAchado::Dependencias, SeveridadeAchado::Informativa,
                'Laravel detectado', 'O pacote laravel/framework foi localizado nas dependências de produção.',
                ['chave' => 'require.laravel/framework', 'restricao' => $requeridas['laravel/framework']], caminhoArquivo: 'composer.json',
            );
        }

        $todas = array_merge($requeridas, $requeridasDesenvolvimento);
        $gruposFerramentas = [
            ['codigo' => 'composer.ferramenta_testes_ausente', 'categoria' => CategoriaAchado::Testes, 'titulo' => 'Ferramenta de testes ausente', 'pacotes' => ['phpunit/phpunit', 'pestphp/pest'], 'descricao' => 'PHPUnit e Pest não foram encontrados no manifesto.', 'recomendacao' => 'Adicionar PHPUnit ou Pest ao require-dev.'],
            ['codigo' => 'composer.analise_estatica_ausente', 'categoria' => CategoriaAchado::Arquitetura, 'titulo' => 'Análise estática ausente', 'pacotes' => ['phpstan/phpstan', 'larastan/larastan', 'nunomaduro/larastan'], 'descricao' => 'PHPStan e Larastan não foram encontrados no manifesto.', 'recomendacao' => 'Adicionar PHPStan ou Larastan ao require-dev.'],
            ['codigo' => 'composer.formatador_ausente', 'categoria' => CategoriaAchado::Estilo, 'titulo' => 'Formatador ausente', 'pacotes' => ['laravel/pint', 'friendsofphp/php-cs-fixer'], 'descricao' => 'Pint e PHP-CS-Fixer não foram encontrados no manifesto.', 'recomendacao' => 'Adicionar Pint ou PHP-CS-Fixer ao require-dev.'],
        ];

        foreach ($gruposFerramentas as $grupo) {
            if (array_intersect($grupo['pacotes'], array_keys($todas)) === []) {
                $achados[] = $this->achado(
                    $grupo['codigo'], $grupo['categoria'], SeveridadeAchado::Media,
                    $grupo['titulo'], $grupo['descricao'],
                    ['chave' => 'require-dev', 'pacotes_procurados' => $grupo['pacotes']],
                    $grupo['recomendacao'], 'composer.json',
                );
            }
        }

        return $achados;
    }

    /** @return array<string, string> */
    private function normalizarDependencias(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        return array_filter($valor, fn (mixed $restricao, mixed $nome): bool => is_string($nome) && is_string($restricao), ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, string>|null */
    private function lerVersoesInstaladas(string $diretorioProjeto): ?array
    {
        $caminho = $diretorioProjeto.DIRECTORY_SEPARATOR.'composer.lock';

        if (! $this->arquivoLegivelNaRaiz($caminho, $diretorioProjeto)) {
            return null;
        }

        $lock = $this->lerJson($caminho, self::LIMITE_COMPOSER_LOCK);

        if ($lock === null) {
            return null;
        }

        $versoes = [];

        $pacotes = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];
        $pacotesDesenvolvimento = is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : [];

        foreach (array_merge($pacotes, $pacotesDesenvolvimento) as $pacote) {
            if (is_array($pacote) && is_string($pacote['name'] ?? null) && is_string($pacote['version'] ?? null)) {
                $versoes[$pacote['name']] = $pacote['version'];
            }
        }

        return $versoes;
    }

    private function lerJson(string $caminho, int $limite): ?array
    {
        $tamanho = filesize($caminho);

        if ($tamanho === false || $tamanho > $limite) {
            return null;
        }

        $conteudo = file_get_contents($caminho);

        try {
            $dados = json_decode($conteudo ?: '', true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($dados) ? $dados : null;
    }

    private function arquivoLegivelNaRaiz(string $caminho, string $raiz): bool
    {
        if (! is_file($caminho) || ! is_readable($caminho) || is_link($caminho)) {
            return false;
        }

        $real = realpath($caminho);
        $raizReal = realpath($raiz);

        return $real !== false && $raizReal !== false && str_starts_with($real, rtrim($raizReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function achado(
        string $codigo,
        CategoriaAchado $categoria,
        SeveridadeAchado $severidade,
        string $titulo,
        string $descricao,
        array $evidencia,
        ?string $recomendacao = null,
        ?string $caminhoArquivo = null,
    ): DadosAchado {
        return new DadosAchado($codigo, $categoria, $severidade, $titulo, $descricao, $recomendacao, $caminhoArquivo, evidencia: $evidencia, metadados: ['analisador' => 'composer', 'versao' => self::VERSAO]);
    }
}
