<?php

namespace Database\Seeders;

use App\Enums\CalculoTaxa;
use App\Enums\FaseTorneio;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoEvento;
use App\Enums\TipoItem;
use App\Models\EstruturaBlinds;
use App\Models\Evento;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\RegraPremiacao;
use App\Models\Usuario;
use App\Services\Jogo\Relogio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Noite de referência (docs/prototipo, mesmos jogadores e lugares): 12 jogadores, 22 entradas,
 * R$ 2.100 arrecadados, taxa "Casa" 10% = R$ 210, prize pool R$ 1.890 → 950 / 565 / 375 (945 / 567 / 378 sem arredondar).
 *
 * Fica em andamento, no intervalo (intervalo aberto, add-ons feitos, ninguém eliminado), como a
 * tela ao vivo do protótipo; as fases 3 e 4 continuam a mesma noite até o fechamento. Usa a estrutura
 * Turbo 15 min (v2), com o relógio pausado no nível 6 (100 / 200) com 14:32, como no protótipo.
 *
 * As entradas são gravadas direto, com o horário de cada uma (chegada, rebuys, intervalo), para a
 * noite ter história nas telas. Jogo\NoiteDeReferenciaTest joga a mesma noite pelas Actions e chega
 * nos mesmos números. O DatabaseSeeder só chama este seeder no ambiente local.
 */
class PokerNight18Seeder extends Seeder
{
    public const NOME = 'Poker Night #18';

    /** nome, apelido, mesa, lugar, buy-in (simples|duplo), rebuys, add-ons */
    public const JOGADORES = [
        ['João', null, 1, 1, 'simples', 0, 1],
        ['Lucas', null, 1, 2, 'simples', 1, 0],
        ['Carlos', null, 1, 3, 'simples', 1, 1],
        ['Rafael', null, 1, 5, 'duplo', 0, 0],
        ['André', null, 1, 6, 'simples', 1, 0],
        ['Bruno', null, 1, 7, 'simples', 0, 0],
        ['Pedro', null, 1, 8, 'simples', 1, 0],
        ['Brendon', null, 2, 1, 'simples', 1, 1],
        ['Marcos', 'Marcão', 2, 3, 'simples', 1, 1],
        ['Thiago', null, 2, 4, 'simples', 0, 0],
        ['Diego', null, 2, 6, 'simples', 0, 0],
        ['Felipe', null, 2, 8, 'simples', 0, 0],
    ];

    /** mesa, dealer */
    private const MESAS = [
        1 => ['Mesa 01', 'Sérgio'],
        2 => ['Mesa 02', 'Paulo'],
    ];

    public function run(): void
    {
        if (Jogo::query()->where('nome', self::NOME)->exists()) {
            return;
        }

        $this->call(EstruturasBlindsSeeder::class);

        DB::transaction(function () {
            $inicio = Carbon::parse('2026-10-04 20:00', config('app.timezone'));

            $jogo = Jogo::query()->create([
                'modalidade' => Modalidade::Torneio,
                'nome' => self::NOME,
                'data' => $inicio->toDateString(),
                'horario' => '20:00',
                'metodo_premiacao' => MetodoPremiacao::Regra,
                'lugares_por_mesa' => 9,
                'tamanho_mesa_final' => 9,
                'estrutura_blinds_id' => EstruturaBlinds::query()->where('nome', EstruturasBlindsSeeder::TURBO)->value('id'),
                'nivel_atual' => 6,
                'blinds_atual' => '100 / 200',
            ]);

            $itens = $this->itens($jogo);
            $jogo->taxas()->create(['nome' => 'Casa', 'calculo' => CalculoTaxa::Percentual, 'valor' => 10, 'ordem' => 0]);
            $this->premiacao($jogo, count(self::JOGADORES));
            $mesas = $this->mesas($jogo, $inicio);

            $this->jogadoresEEntradas($jogo, $itens, $mesas, $inicio);

            // Status e fase só mudam pelas Actions no app; aqui a noite já chega no intervalo.
            $jogo->forceFill([
                'status' => StatusJogo::EmAndamento,
                'fase' => FaseTorneio::Intervalo,
                'iniciado_em' => $inicio->copy()->addMinutes(5),
                'intervalo_aberto_em' => $inicio->copy()->addMinutes(100),
            ])->save();

            // Relógio como a faixa do protótipo: nível 6 (100 / 200), pausado com 14:32.
            if ($nivel6 = $jogo->estruturaBlinds?->niveis->where('e_intervalo', false)->values()->get(5)) {
                app(Relogio::class)->posicionar($jogo, $nivel6, 14 * 60 + 32, false);
                $jogo->forceFill(['relogio_iniciado_em' => $inicio->copy()->addMinutes(5)])->save();
            }
        });
    }

    /** @return array<string, Item> buyin, duplo, triplo, rebuy, addon */
    private function itens(Jogo $jogo): array
    {
        $criar = fn (TipoItem $tipo, string $nome, int $valor, int $fichas, ?int $limite, bool $padrao, int $ordem) => $jogo->itens()->create([
            'tipo' => $tipo,
            'nome' => $nome,
            'valor' => $valor,
            'fichas' => $fichas,
            'limite_por_jogador' => $limite,
            'padrao' => $padrao,
            'ordem' => $ordem,
        ]);

        // tipo, nome, R$, fichas, limite por jogador, padrão, ordem
        return [
            'buyin' => $criar(TipoItem::Buyin, 'Buy-in', 100, 10000, 1, true, 0),
            'rebuy' => $criar(TipoItem::Rebuy, 'Rebuy', 100, 10000, null, true, 1),
            'addon' => $criar(TipoItem::Addon, 'Add-on', 50, 10000, 1, true, 2),
            'duplo' => $criar(TipoItem::Buyin, 'Buy-in duplo', 200, 20000, 1, false, 3),
            'triplo' => $criar(TipoItem::Buyin, 'Buy-in triplo', 300, 30000, 1, false, 4),
        ];
    }

    private function premiacao(Jogo $jogo, int $jogadores): void
    {
        $regra = RegraPremiacao::query()->paraJogadores($jogadores)->first()
            ?? throw new RuntimeException('Sem regra de premiação: rode o RegrasPremiacaoSeeder antes.');

        foreach ($regra->percentuais as $indice => $percentual) {
            $jogo->premiacoes()->create(['posicao' => $indice + 1, 'percentual' => $percentual]);
        }
    }

    /** @return array<int, JogoMesa> */
    private function mesas(Jogo $jogo, Carbon $inicio): array
    {
        $evento = Evento::query()->create([
            'tipo' => TipoEvento::Jogo,
            'nome' => self::NOME,
            'data' => $inicio->toDateString(),
            'hora_inicio' => '20:00',
            'jogo_id' => $jogo->id,
        ]);

        $jogoMesas = [];

        foreach (self::MESAS as $numero => [$nomeMesa, $nomeDealer]) {
            $mesa = Mesa::query()->firstOrCreate(['nome' => $nomeMesa], ['lugares' => 9]);
            $dealer = Usuario::query()->firstOrCreate(['nome' => $nomeDealer], ['pode_ser_dealer' => true]);

            $jogoMesas[$numero] = $jogo->mesas()->create([
                'mesa_id' => $mesa->id,
                'dealer_id' => $dealer->id,
                'lugares' => 9,
                'ordem' => $numero,
            ]);

            $evento->mesas()->attach($mesa->id);
        }

        return $jogoMesas;
    }

    /**
     * @param  array<string, Item>  $itens
     * @param  array<int, JogoMesa>  $mesas
     */
    private function jogadoresEEntradas(Jogo $jogo, array $itens, array $mesas, Carbon $inicio): void
    {
        $lancamentos = []; // [minuto, participante, item]

        foreach (self::JOGADORES as $ordem => [$nome, $apelido, $mesa, $lugar, $buyin, $rebuys, $addons]) {
            $usuario = Usuario::query()->firstOrCreate(['nome' => $nome], ['apelido' => $apelido]);

            $participante = $jogo->participantes()->create([
                'usuario_id' => $usuario->id,
                'jogo_mesa_id' => $mesas[$mesa]->id,
                'lugar' => $lugar,
            ]);
            $participante->forceFill(['status' => StatusParticipante::Jogando])->save();

            // Buy-ins na chegada, rebuys durante a primeira hora e meia, add-ons no intervalo.
            $lancamentos[] = [$ordem, $participante, $buyin === 'duplo' ? $itens['duplo'] : $itens['buyin']];

            for ($i = 0; $i < $rebuys; $i++) {
                $lancamentos[] = [30 + $ordem * 5, $participante, $itens['rebuy']];
            }

            for ($i = 0; $i < $addons; $i++) {
                $lancamentos[] = [100 + $ordem, $participante, $itens['addon']];
            }
        }

        usort($lancamentos, fn ($a, $b) => $a[0] <=> $b[0]);

        foreach ($lancamentos as [$minuto, $participante, $item]) {
            $this->lancar($jogo, $participante, $item, $inicio->copy()->addMinutes($minuto));
        }
    }

    private function lancar(Jogo $jogo, Participante $participante, Item $item, Carbon $quando): void
    {
        $entrada = $jogo->entradas()->make([
            'uuid' => (string) Str::uuid(),
            'participante_id' => $participante->id,
            'jogo_item_id' => $item->id,
            'tipo' => $item->tipo,
            'valor' => $item->valor,
            'fichas' => $item->fichas,
            'percentual_premiacao' => $item->percentual_premiacao,
        ]);

        $entrada->forceFill(['created_at' => $quando, 'updated_at' => $quando])->save();
    }
}
