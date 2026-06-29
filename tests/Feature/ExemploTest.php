<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExemploTest extends TestCase
{
    #[Test]
    public function teste_pagina_inicial_redireciona_para_o_painel(): void
    {
        $resposta = $this->get('/');

        $resposta->assertRedirect('/admin');
    }

    #[Test]
    public function teste_painel_disponibiliza_login_e_cadastro(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/register')->assertOk();
    }
}
