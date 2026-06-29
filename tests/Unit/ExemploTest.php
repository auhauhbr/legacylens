<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExemploTest extends TestCase
{
    #[Test]
    public function teste_verdadeiro_e_verdadeiro(): void
    {
        $this->assertTrue(true);
    }
}
