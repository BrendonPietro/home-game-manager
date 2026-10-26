<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AtualizarNivel;
use App\Models\Jogo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class NivelTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    private function nivel(?int $nivel, ?string $blinds, ?Jogo $jogo = null): Jogo
    {
        return app(AtualizarNivel::class)($jogo ?? $this->jogo, $nivel, $blinds, $this->admin);
    }

    public function test_grava_nivel_e_blinds_com_a_barra_espacada(): void
    {
        $this->nivel(7, ' 200/400/ 50 ');
        $this->assertSame([7, '200 / 400 / 50'], [$this->jogo->fresh()->nivel_atual, $this->jogo->fresh()->blinds_atual]);

        $this->nivel(null, '');
        $this->assertSame([null, null], [$this->jogo->fresh()->nivel_atual, $this->jogo->fresh()->blinds_atual]);
    }

    public function test_regras(): void
    {
        $this->assertRegra('O nível vai de 1 a 99.', fn () => $this->nivel(0, '100 / 200'));
        $this->assertRegra('Os blinds cabem em 40 caracteres.', fn () => $this->nivel(1, str_repeat('9', 41)));
        $this->assertRegra('Nível e blinds só com o jogo em andamento.', fn () => $this->nivel(1, '100 / 200', Jogo::factory()->agendado()->create()));

        $this->expectException(AuthorizationException::class);
        app(AtualizarNivel::class)($this->jogo, 2, '100 / 200', $this->operador);
    }
}
