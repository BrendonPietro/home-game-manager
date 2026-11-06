<?php

namespace Tests\Unit;

use App\Support\GeradorDeNiveis;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeradorDeNiveisTest extends TestCase
{
    private function nivel(int $small, int $big, int $ante = 0, int $minutos = 15): array
    {
        return ['e_intervalo' => false, 'small_blind' => $small, 'big_blind' => $big, 'ante' => $ante, 'duracao_minutos' => $minutos];
    }

    private function intervalo(int $minutos = 10): array
    {
        return ['e_intervalo' => true, 'small_blind' => null, 'big_blind' => null, 'ante' => 0, 'duracao_minutos' => $minutos];
    }

    /** "200/400", "200/400 a400" ou "[10]" (intervalo) e os minutos, para comparar fácil. */
    private function resumo(array $niveis): array
    {
        return array_map(fn (array $n): string => $n['e_intervalo']
            ? "[{$n['duracao_minutos']}]"
            : "{$n['small_blind']}/{$n['big_blind']}".($n['ante'] ? " a{$n['ante']}" : '')." {$n['duracao_minutos']}m", $niveis);
    }

    private function continuar(array $niveis, int $quantos = 5): array
    {
        $padrao = GeradorDeNiveis::padrao($niveis);

        return $this->resumo(GeradorDeNiveis::gerar($niveis, $quantos, $padrao['tipo'], $padrao['valor'], $padrao['minutos']));
    }

    public function test_dobrando(): void
    {
        $niveis = [$this->nivel(25, 50), $this->nivel(50, 100), $this->nivel(100, 200)];

        $this->assertSame(['tipo' => 'dobrar', 'valor' => 2, 'minutos' => 15, 'minutos_intervalo' => 15], GeradorDeNiveis::padrao($niveis));
        $this->assertSame(['200/400 15m', '400/800 15m', '800/1600 15m'], $this->continuar($niveis, 3));
    }

    public function test_somando_o_mesmo_valor(): void
    {
        // Big subindo de 100 em 100.
        $niveis = [$this->nivel(50, 100, 0, 20), $this->nivel(100, 200, 0, 20), $this->nivel(150, 300, 0, 20)];

        $this->assertSame(['somar', 100], [GeradorDeNiveis::padrao($niveis)['tipo'], GeradorDeNiveis::padrao($niveis)['valor']]);
        $this->assertSame(['200/400 20m', '250/500 20m', '300/600 20m'], $this->continuar($niveis, 3));
    }

    public function test_multiplicando_com_ante_igual_ao_big(): void
    {
        $niveis = [$this->nivel(100, 200, 200), $this->nivel(150, 300, 300), $this->nivel(225, 450, 450)];

        $this->assertSame(['multiplicar', 1.5], [GeradorDeNiveis::padrao($niveis)['tipo'], GeradorDeNiveis::padrao($niveis)['valor']]);
        // 675 vira 350 / 700: o small arredonda no passo do big e o big é o dobro.
        $this->assertSame(['350/700 a700 15m', '500/1000 a1000 15m', '800/1600 a1600 15m'], $this->continuar($niveis, 3));
    }

    public function test_sem_padrao_usa_a_razao_do_ultimo_salto(): void
    {
        // O fim da Turbo 15 min: 3.000 → 4.000 → 6.000 (×1,33 e ×1,5).
        $niveis = [$this->nivel(1500, 3000), $this->nivel(2000, 4000), $this->nivel(3000, 6000)];

        $this->assertSame(['multiplicar', 1.5], [GeradorDeNiveis::padrao($niveis)['tipo'], GeradorDeNiveis::padrao($niveis)['valor']]);
        $this->assertSame(['4500/9000 15m', '7000/14000 15m', '10500/21000 15m'], $this->continuar($niveis, 3));
    }

    public function test_um_nivel_so_soma_o_big(): void
    {
        $this->assertSame(['200/400 15m', '300/600 15m'], $this->continuar([$this->nivel(100, 200)], 2));
    }

    public function test_intervalo_nao_conta_no_padrao_e_os_minutos_vem_do_ultimo_nivel(): void
    {
        $niveis = [$this->nivel(100, 200, 0, 20), $this->intervalo(10), $this->nivel(200, 400, 0, 25)];
        $padrao = GeradorDeNiveis::padrao($niveis);

        $this->assertSame(['dobrar', 25, 10], [$padrao['tipo'], $padrao['minutos'], $padrao['minutos_intervalo']]);
        $this->assertSame(['400/800 25m', '800/1600 25m'], $this->continuar($niveis, 2));
    }

    public function test_intervalo_a_cada_n_niveis_menos_depois_do_ultimo(): void
    {
        $gerados = GeradorDeNiveis::gerar([$this->nivel(100, 200)], 6, GeradorDeNiveis::DOBRAR, 2, 20, 3, 15);

        $this->assertSame(['200/400 20m', '400/800 20m', '800/1600 20m', '[15]', '1600/3200 20m', '3200/6400 20m', '6500/13000 20m'], $this->resumo($gerados));
        $this->assertSame('Intervalo', $gerados[3]['nome_intervalo']);
    }

    public function test_padrao_escolhido_a_mao(): void
    {
        $niveis = [$this->nivel(100, 200)];

        $this->assertSame(['150/300 30m', '225/450 30m'], $this->resumo(GeradorDeNiveis::gerar($niveis, 2, GeradorDeNiveis::MULTIPLICAR, '1,5', 30)));
        $this->assertSame(['150/300 30m', '200/400 30m'], $this->resumo(GeradorDeNiveis::gerar($niveis, 2, GeradorDeNiveis::SOMAR, 100, 30)));
    }

    public function test_big_sempre_sobe_mesmo_arredondando(): void
    {
        // ×1,05 de 100 / 200 daria 210 → arredonda para 200 de novo: sobe um passo.
        $gerados = GeradorDeNiveis::gerar([$this->nivel(100, 200)], 3, GeradorDeNiveis::MULTIPLICAR, 1.05, 15);

        $grandes = array_column($gerados, 'big_blind');
        $this->assertSame($grandes, array_values(array_unique($grandes)));
        $this->assertGreaterThan(200, $grandes[0]);
        $this->assertTrue($grandes[0] < $grandes[1] && $grandes[1] < $grandes[2]);
    }

    public function test_small_fora_da_metade_arredonda_cada_um(): void
    {
        $this->assertSame(['275/400 15m', '325/500 15m'], $this->continuar([$this->nivel(100, 200), $this->nivel(200, 300)], 2));
    }

    public function test_arredondar_para_valor_de_ficha(): void
    {
        $this->assertSame([7, 10, 100, 225, 1600, 13500, 20500, 0], array_map(
            fn ($v) => GeradorDeNiveis::arredondar($v),
            [7, 12, 99, 237, 1640, 13500, 20250, 0],
        ));
    }

    public function test_sem_nivel_de_blinds_ou_valor_errado_recusa(): void
    {
        foreach ([
            [[$this->intervalo()], GeradorDeNiveis::DOBRAR, 2, 'ao menos um nível com blinds'],
            [[$this->nivel(100, 200)], GeradorDeNiveis::MULTIPLICAR, 1, 'maior que 1'],
            [[$this->nivel(100, 200)], GeradorDeNiveis::SOMAR, 0, 'pelo menos 1'],
        ] as [$niveis, $tipo, $valor, $mensagem]) {
            try {
                GeradorDeNiveis::gerar($niveis, 3, $tipo, $valor, 15);
                $this->fail("Devia recusar: {$mensagem}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($mensagem, $e->getMessage());
            }
        }
    }
}
