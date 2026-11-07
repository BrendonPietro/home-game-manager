<?php

namespace Tests\Concerns;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\DefinirResultado;
use App\Actions\Jogo\DesfazerAcao;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\FinalizarJogo;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Actions\Jogo\RegistrarEntrada;
use App\Actions\Jogo\RegistrarPagamento;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Eliminacao;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\Pagamento;
use App\Models\Participante;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Support\Str;

/**
 * Noite pronta para as Actions do caixa: torneio em andamento (fase de rebuys) com buy-in, rebuy
 * e add-on padrão de R$ 100, uma mesa de 9 lugares, um Admin e um Operador do site.
 * Usar junto com RefreshDatabase.
 */
trait MontaNoite
{
    protected Usuario $admin;

    protected Usuario $operador;

    protected Jogo $jogo;

    protected JogoMesa $mesa;

    protected function montarNoite(?Jogo $jogo = null): void
    {
        $this->seed([PermissoesSeeder::class, RegrasPremiacaoSeeder::class]);

        $this->admin = Usuario::factory()->comAcesso()->create();
        $this->admin->assignRole('Admin');
        $this->operador = Usuario::factory()->comAcesso()->create();
        $this->operador->assignRole('Operador');

        $this->jogo = $jogo ?? Jogo::factory()->emAndamento()->comEntradasPadrao()->create();
        $this->mesa = $this->jogo->mesas()->create(['mesa_id' => Mesa::factory()->create(['nome' => 'Mesa 01'])->id, 'lugares' => 9, 'ordem' => 1]);
    }

    /** Jogador sentado no próximo lugar e jogando. */
    protected function jogador(string $nome): Participante
    {
        $participante = $this->jogo->participantes()->create([
            'usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id,
            'jogo_mesa_id' => $this->mesa->id,
            'lugar' => (int) $this->mesa->participantes()->max('lugar') + 1,
        ]);
        $participante->forceFill(['status' => StatusParticipante::Jogando])->save();

        return $participante;
    }

    protected function item(TipoItem $tipo): Item
    {
        return $this->jogo->itens()->where('tipo', $tipo)->where('padrao', true)->firstOrFail();
    }

    /** @param  list<int>  $opcionais */
    protected function entrar(Participante $participante, Item|TipoItem $item, array $opcionais = [], ?Usuario $quem = null, ?string $uuid = null): Entrada
    {
        return app(RegistrarEntrada::class)(
            $participante,
            $item instanceof Item ? $item : $this->item($item),
            $quem ?? $this->admin,
            $uuid ?? (string) Str::uuid(),
            $opcionais,
        );
    }

    protected function pagar(Participante $participante, int $centavos, DirecaoPagamento $direcao = DirecaoPagamento::JogadorPagou, ?string $uuid = null): Pagamento
    {
        return app(RegistrarPagamento::class)($participante, $direcao, $centavos, FormaPagamento::Pix, $this->operador, $uuid ?? (string) Str::uuid());
    }

    protected function eliminar(Participante $participante): Eliminacao
    {
        return app(RegistrarEliminacao::class)($participante, $this->operador);
    }

    protected function desfazer(Participante $participante, string $motivo = 'Lançado errado'): string
    {
        return app(DesfazerAcao::class)($participante, $motivo, $this->admin);
    }

    /**
     * Monta e finaliza um torneio de três (Ana, Beto, Caio, buy-in de R$ 100): R$ 300 → 195 / 105.
     * Ana pagou R$ 100 e foi a 1ª (o caixa deve 195), Beto 2º (o caixa deve 5), Caio 3º (deve 100).
     *
     * @return list<Participante> Ana, Beto e Caio
     */
    protected function noiteFinalizadaDeTres(string $nome = 'Poker Night #19', string $data = '2026-10-11'): array
    {
        $this->montarNoite(Jogo::factory()->emAndamento()->comEntradasPadrao()->create(['nome' => $nome, 'data' => $data]));

        $jogadores = array_map(fn (string $quem) => $this->jogador($quem), ['Ana', 'Beto', 'Caio']);
        foreach ($jogadores as $jogador) {
            $this->entrar($jogador, TipoItem::Buyin);
        }
        [$ana, $beto, $caio] = $jogadores;
        $this->pagar($ana, 10000);

        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $this->eliminar($caio);
        app(DefinirResultado::class)($this->jogo, [$ana->id => 1, $beto->id => 2], $this->admin);
        app(FinalizarJogo::class)($this->jogo, $this->admin);

        return $jogadores;
    }

    /** A ação tem de ser barrada por uma regra do jogo, com esta mensagem. */
    protected function assertRegra(string $mensagem, callable $acao): void
    {
        try {
            $acao();
        } catch (RegraDoJogo $erro) {
            $this->assertSame($mensagem, $erro->getMessage());

            return;
        }

        $this->fail("Deixou passar: {$mensagem}");
    }
}
