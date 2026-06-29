<?php

namespace App\Dominio\Analises\Analisadores;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\CategoriaAchado;
use App\Enums\SeveridadeAchado;

class CiAnalyzer
{
    public const VERSAO = '1.0.0';

    private const LIMITE_ARQUIVO = 1_048_576;

    private const LIMITE_ARQUIVOS = 50;

    /** @return list<DadosAchado> */
    public function analisar(string $diretorioProjeto): array
    {
        $arquivos = $this->localizarArquivos($diretorioProjeto);

        if ($arquivos === []) {
            return [$this->achado(
                'ci.configuracao_ausente',
                SeveridadeAchado::Media,
                'Configuração de CI ausente',
                'Não foi encontrada configuração convencional de GitHub Actions, GitLab CI ou Bitbucket Pipelines.',
                ['provedores_procurados' => ['acoes_github', 'ci_gitlab', 'pipelines_bitbucket'], 'estado' => 'ausente'],
                'Adicionar uma pipeline de integração contínua com testes e verificações de qualidade.',
            )];
        }

        $conteudoCombinado = '';
        $arquivosLidos = [];
        $arquivosIgnorados = [];

        foreach ($arquivos as $caminhoRelativo => $caminhoAbsoluto) {
            $conteudo = $this->lerArquivoLimitado($caminhoAbsoluto);

            if ($conteudo === null) {
                $arquivosIgnorados[] = $caminhoRelativo;

                continue;
            }

            $arquivosLidos[] = $caminhoRelativo;
            $conteudoCombinado .= "\n".$conteudo;
        }

        $comandos = $this->detectarComandos($conteudoCombinado);
        $achados = [$this->achado(
            'ci.configuracao_detectada',
            SeveridadeAchado::Informativa,
            'Configuração de CI detectada',
            'Foram encontrados arquivos convencionais de integração contínua.',
            ['arquivos' => array_keys($arquivos), 'arquivos_lidos' => $arquivosLidos, 'arquivos_ignorados' => $arquivosIgnorados],
            caminhoArquivo: array_key_first($arquivos),
        )];

        if ($arquivosLidos === []) {
            $achados[] = $this->achado(
                'ci.configuracao_nao_analisavel',
                SeveridadeAchado::Baixa,
                'Configuração de CI não analisável',
                'Os arquivos de CI detectados não puderam ser lidos com segurança ou excedem o limite de tamanho.',
                ['arquivos_ignorados' => $arquivosIgnorados, 'limite_bytes_por_arquivo' => self::LIMITE_ARQUIVO],
                'Manter os arquivos de CI legíveis e com tamanho adequado para análise.',
            );

            return $achados;
        }

        $achados[] = $this->achado(
            'ci.comandos_detectados',
            SeveridadeAchado::Informativa,
            'Comandos de CI avaliados',
            'A configuração de CI foi inspecionada passivamente em busca de testes e verificações de qualidade.',
            ['comandos' => $comandos, 'arquivos_lidos' => $arquivosLidos],
            caminhoArquivo: $arquivosLidos[0],
        );

        if (! $comandos['teste_artisan'] && ! $comandos['phpunit'] && ! $comandos['pest']) {
            $achados[] = $this->achado(
                'ci.testes_ausentes',
                SeveridadeAchado::Media,
                'Execução de testes ausente no CI',
                'A configuração de CI não apresenta sinais de execução por Artisan, PHPUnit ou Pest.',
                ['comandos_de_teste' => array_intersect_key($comandos, array_flip(['teste_artisan', 'phpunit', 'pest'])), 'detectado' => false],
                'Adicionar a execução automatizada da suíte de testes à pipeline.',
                $arquivosLidos[0],
            );
        }

        $qualidade = array_intersect_key($comandos, array_flip(['pint', 'phpstan', 'larastan', 'validacao_composer']));

        if (! in_array(true, $qualidade, true)) {
            $achados[] = $this->achado(
                'ci.qualidade_ausente',
                SeveridadeAchado::Baixa,
                'Verificações de qualidade ausentes no CI',
                'Não foram encontrados sinais de Pint, PHPStan, Larastan ou composer validate na pipeline.',
                ['comandos_de_qualidade' => $qualidade, 'detectado' => false],
                'Adicionar ao menos uma verificação automatizada de estilo, análise estática ou validação do Composer.',
                $arquivosLidos[0],
            );
        }

        return $achados;
    }

    /** @return array<string, string> */
    private function localizarArquivos(string $diretorioProjeto): array
    {
        $candidatos = [];
        $diretorioFluxos = $diretorioProjeto.DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows';

        if ($this->diretorioSeguro($diretorioFluxos, $diretorioProjeto)) {
            $nomes = scandir($diretorioFluxos) ?: [];
            sort($nomes);

            foreach ($nomes as $nome) {
                if (count($candidatos) >= self::LIMITE_ARQUIVOS || preg_match('/\.(?:ya?ml)$/i', $nome) !== 1) {
                    continue;
                }

                $caminho = $diretorioFluxos.DIRECTORY_SEPARATOR.$nome;

                if ($this->arquivoSeguro($caminho, $diretorioProjeto)) {
                    $candidatos['.github/workflows/'.$nome] = $caminho;
                }
            }
        }

        foreach (['.gitlab-ci.yml', 'bitbucket-pipelines.yml'] as $nome) {
            $caminho = $diretorioProjeto.DIRECTORY_SEPARATOR.$nome;

            if ($this->arquivoSeguro($caminho, $diretorioProjeto)) {
                $candidatos[$nome] = $caminho;
            }
        }

        return $candidatos;
    }

    /** @return array<string, bool> */
    private function detectarComandos(string $conteudo): array
    {
        $padroes = [
            'teste_artisan' => '~(?:^|[\s"\'`./])php\s+artisan\s+test\b~mi',
            'phpunit' => '~(?:^|[\s"\'`./])(?:vendor/bin/)?phpunit\b~mi',
            'pest' => '~(?:^|[\s"\'`./])(?:vendor/bin/)?pest\b~mi',
            'pint' => '~(?:^|[\s"\'`./])(?:vendor/bin/)?pint\b~mi',
            'phpstan' => '~(?:^|[\s"\'`./])(?:vendor/bin/)?phpstan\b~mi',
            'larastan' => '~(?:^|[\s"\'`./])(?:vendor/bin/)?larastan\b~mi',
            'validacao_composer' => '~\bcomposer\s+validate\b~mi',
        ];
        $detectados = [];

        foreach ($padroes as $comando => $padrao) {
            $detectados[$comando] = preg_match($padrao, $conteudo) === 1;
        }

        return $detectados;
    }

    private function lerArquivoLimitado(string $caminho): ?string
    {
        $tamanho = filesize($caminho);

        if ($tamanho === false || $tamanho > self::LIMITE_ARQUIVO) {
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
            CategoriaAchado::IntegracaoContinua,
            $severidade,
            $titulo,
            $descricao,
            $recomendacao,
            $caminhoArquivo,
            evidencia: $evidencia,
            metadados: ['analisador' => 'ci', 'versao' => self::VERSAO],
        );
    }
}
