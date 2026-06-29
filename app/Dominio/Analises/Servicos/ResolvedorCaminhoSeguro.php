<?php

namespace App\Dominio\Analises\Servicos;

use InvalidArgumentException;

class ResolvedorCaminhoSeguro
{
    /**
     * Resolve e valida exclusivamente o diretório cadastrado para um projeto.
     */
    public function resolver(string $caminhoCadastrado): string
    {
        if ($caminhoCadastrado === '' || $this->contemTravessia($caminhoCadastrado)) {
            throw new InvalidArgumentException('O caminho cadastrado é inválido.');
        }

        $caminhoReal = realpath($caminhoCadastrado);

        if ($caminhoReal === false) {
            throw new InvalidArgumentException('O caminho cadastrado não existe.');
        }

        if (! is_dir($caminhoReal)) {
            throw new InvalidArgumentException('O caminho cadastrado não é um diretório.');
        }

        if ($this->diretorioSensivel($caminhoReal)) {
            throw new InvalidArgumentException('O diretório cadastrado é sensível e não pode ser analisado.');
        }

        return $caminhoReal;
    }

    private function contemTravessia(string $caminho): bool
    {
        $segmentos = preg_split('~[\\\\/]+~', $caminho) ?: [];

        return in_array('..', $segmentos, true);
    }

    private function diretorioSensivel(string $caminhoReal): bool
    {
        $normalizado = rtrim($caminhoReal, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;

        return in_array($normalizado, config('legacylens.sensitive_directories', []), true);
    }
}
