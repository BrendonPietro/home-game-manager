<?php

namespace Tests\Feature\Painel;

use App\Actions\Jogo\FinalizarJogo;
use App\Actions\Jogo\RegistrarCashOut;
use App\Enums\DirecaoPagamento;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Usuario;
use App\Services\Jogo\Saldos;
use App\Services\Painel\Indicadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** "Valores pendentes" do dashboard: o saldo feito no banco (Participante::scopeComSaldo) bate com o Saldos. */
class PendenciasTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    public function test_pendencias_batem_com_os_saldos_de_cada_jogo(): void
    {
        [$ana, $beto, $caio] = $this->noiteFinalizadaDeTres();
        $torneio = $this->jogo;
        $this->pagar($caio, 4000);                                     // Caio paga parte do que deve
        $this->pagar($beto, 500, DirecaoPagamento::JogadorRecebeu);    // Beto recebe os R$ 5 dele

        // Um cash game finalizado, com cash-out, no mesmo período.
        $cash = Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create(['valor_ficha' => '0.01']);
        $mesa = $cash->mesas()->create(['mesa_id' => Mesa::factory()->create()->id, 'lugares' => 9]);
        $sentar = function (string $nome, int $lugar) use ($cash, $mesa) {
            $participante = $cash->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id, 'jogo_mesa_id' => $mesa->id, 'lugar' => $lugar]);
            $participante->forceFill(['status' => StatusParticipante::Jogando])->save();

            return $participante;
        };
        $dudu = $sentar('Dudu', 1);
        $edu = $sentar('Edu', 2);
        $this->entrar($dudu, $cash->itens()->where('tipo', TipoItem::Buyin)->first());
        $this->entrar($edu, $cash->itens()->where('tipo', TipoItem::Buyin)->first());
        app(RegistrarCashOut::class)($dudu, 15000, $this->admin);
        app(RegistrarCashOut::class)($edu, 5000, $this->admin);
        app(FinalizarJogo::class)($cash, $this->admin);

        $esperado = [];
        foreach ([$torneio, $cash] as $jogo) {
            foreach (app(Saldos::class)->doJogo($jogo->fresh())->reject->quitado() as $id => $saldo) {
                $esperado[$id] = $saldo->saldo;
            }
        }
        ksort($esperado);

        $pendencias = app(Indicadores::class)->pendencias();
        $obtido = $pendencias['jogadores']->mapWithKeys(fn (array $l) => [$l['participante']->id => $l['saldo']])->sortKeys()->all();

        $this->assertSame($esperado, $obtido);
        $this->assertSame([-19500, 6000, -5000, 5000], array_values($obtido)); // Ana, Caio, Dudu, Edu
        $this->assertNotContains($beto->id, array_keys($obtido), 'Beto recebeu: quitado');
        $this->assertSame(11000, $pendencias['aReceber']);
        $this->assertSame(24500, $pendencias['aPagar']);
        $this->assertSame([$torneio->id, $torneio->id, $cash->id, $cash->id], $pendencias['jogadores']->map(fn ($l) => $l['jogo']->id)->all());
    }
}
