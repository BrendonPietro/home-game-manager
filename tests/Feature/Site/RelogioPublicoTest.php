<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\ControlarRelogio;
use App\Actions\Jogo\TrocarLinkDoRelogio;
use App\Enums\ComandoRelogio;
use App\Enums\StatusJogo;
use App\Livewire\Jogo\AoVivo;
use App\Models\Jogo;
use App\Services\Jogo\CopiaDoJogo;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/** v3: link do relógio do torneio (/relogio/{codigo}), sem login e só o relógio; a TV virou ele. */
class RelogioPublicoTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
    }

    private function noite18(): Jogo
    {
        $this->seed(PokerNight18Seeder::class);

        return Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    public function test_todo_jogo_nasce_com_um_codigo_e_a_copia_ganha_outro(): void
    {
        $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]{8}$/', $this->jogo->relogio_codigo);
        $this->assertSame(url('/relogio/'.$this->jogo->relogio_codigo), $this->jogo->linkDoRelogio());

        $copia = app(CopiaDoJogo::class)->copiar($this->jogo, ['nome' => 'Cópia']);
        $this->assertNotSame($this->jogo->relogio_codigo, $copia->relogio_codigo);
        $this->assertNotNull($copia->relogio_codigo);
    }

    public function test_sem_login_mostra_so_o_relogio(): void
    {
        $noite = $this->noite18();

        // Ninguém logado. Relógio parado no nível 6 com 14:32.
        $this->get($noite->linkDoRelogio())
            ->assertOk()
            ->assertSee('Relógio · Poker Night #18', false)
            ->assertSeeHtml('x-data="relogioTorneio"')
            ->assertSeeHtml('data-url="'.route('relogio.estado', $noite->relogio_codigo).'"')
            ->assertSee(['Poker Night #18', 'Nível 6', '14:32', 'Pausado', '100 / 200', 'Depois: Intervalo · 15 min', 'Intervalo em', '1 nível'])
            ->assertSee(['SOM DESLIGADO', 'TELA CHEIA'])
            // Nada de dinheiro nem de jogadores.
            ->assertDontSee(['Prize pool', 'R$', 'Premiação', 'Stack médio', 'Jogando', 'Entradas', 'VOLTAR', 'Intervalo (add-on)']);
    }

    public function test_estado_sem_sessao_para_o_motor(): void
    {
        $noite = $this->noite18();
        app(ControlarRelogio::class)($noite, ComandoRelogio::Continuar, $this->admin);

        $resposta = $this->getJson(route('relogio.estado', $noite->relogio_codigo))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'em_andamento')
            ->assertJsonPath('relogio.rodando', true)
            ->assertJsonPath('relogio.niveis.5.titulo', 'Nível 6')
            ->assertJsonPath('relogio.niveis.6.rotulo', 'Na volta · nível 7')
            ->assertJsonPath('manual', null);

        // Sem sessão nem cookie: a TV pede a cada 2 s e nunca "expira".
        $this->assertSame([], $resposta->headers->getCookies());

        // A chave é a mesma da página: muda quando a página tem de recarregar.
        $this->assertStringContainsString('data-chave="'.$resposta->json('chave').'"', $this->get($noite->linkDoRelogio())->getContent());
    }

    public function test_link_trocado_deixa_de_abrir(): void
    {
        $antigo = $this->jogo->linkDoRelogio();
        $codigoAntigo = $this->jogo->relogio_codigo;

        // Só quem controla o relógio troca.
        $this->assertThrows(fn () => app(TrocarLinkDoRelogio::class)($this->jogo, $this->operador), AuthorizationException::class);

        $novo = app(TrocarLinkDoRelogio::class)($this->jogo, $this->admin);

        $this->assertNotSame($codigoAntigo, $novo->relogio_codigo);
        $this->get($antigo)->assertNotFound()->assertSee('Este link não vale mais');
        $this->getJson(route('relogio.estado', $codigoAntigo))->assertNotFound();
        $this->get($novo->linkDoRelogio())->assertOk();
    }

    public function test_tela_ao_vivo_mostra_copia_e_troca_o_link(): void
    {
        $this->actingAs($this->admin, 'web');

        $tela = Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSeeHtml('wire:click="abrirLink"')
            ->call('abrirLink')
            ->assertSet('modal', 'link')
            ->assertSee(['Relógio nas outras telas', $this->jogo->linkDoRelogio(), 'COPIAR LINK', 'ABRIR', 'GERAR OUTRO LINK']);

        $antes = $this->jogo->relogio_codigo;
        $tela->call('trocarLink')
            ->assertSet('aviso', 'Link do relógio trocado: mande o novo para as outras telas.')
            ->assertSee($this->jogo->fresh()->linkDoRelogio());
        $this->assertNotSame($antes, $this->jogo->fresh()->relogio_codigo);

        // O Operador vê e copia, mas não troca.
        $this->actingAs($this->operador, 'web');
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo->fresh()])
            ->call('abrirLink')
            ->assertSee('COPIAR LINK')
            ->assertDontSee('GERAR OUTRO LINK');
    }

    public function test_tv_antiga_leva_para_o_link(): void
    {
        $this->actingAs($this->operador, 'web');

        $this->get(route('jogos.tv', $this->jogo))->assertRedirect($this->jogo->linkDoRelogio());

        $rascunho = Jogo::factory()->create(['nome' => 'Rascunho']);
        $this->get(route('jogos.tv', $rascunho))
            ->assertRedirect(route('jogos.index'))
            ->assertSessionHas('aviso', 'Rascunho não está disponível no site.');
    }

    public function test_sem_estrutura_mostra_o_nivel_digitado(): void
    {
        $this->jogo->forceFill(['nivel_atual' => 3, 'blinds_atual' => '50 / 100'])->save();

        $this->get($this->jogo->linkDoRelogio())
            ->assertOk()
            ->assertSee(['Nível 3', '50 / 100'])
            ->assertSeeHtml('sem-relogio')
            ->assertDontSee('Intervalo em');

        $this->getJson(route('relogio.estado', $this->jogo->relogio_codigo))
            ->assertJsonPath('relogio', null)
            ->assertJsonPath('manual', ['titulo' => 'Nível 3', 'blinds' => '50 / 100']);
    }

    public function test_antes_de_comecar_depois_de_finalizar_e_fora_do_site(): void
    {
        $noite = $this->noite18();
        $agendado = app(CopiaDoJogo::class)->copiar($noite, ['nome' => 'Poker Night #19', 'data' => '2026-11-11']);
        $agendado->forceFill(['status' => StatusJogo::Agendado])->save();

        // Agendado: quando começa e o nível 1, sem relógio correndo.
        $this->get($agendado->linkDoRelogio())
            ->assertOk()
            ->assertSee(['Poker Night #19', 'O relógio começa quando o torneio for iniciado · nível 1: 10 / 20 · 15 min'])
            ->assertDontSeeHtml('data-relogio=');

        // Finalizado: só "Fim de jogo", sem prêmio nem quem ganhou.
        $noite->forceFill(['status' => StatusJogo::Finalizado])->save();
        $this->get($noite->linkDoRelogio())
            ->assertOk()
            ->assertSee(['Fim de jogo', 'Poker Night #18'])
            ->assertDontSee(['R$', '1º']);

        // Rascunho e modelo não abrem.
        $rascunho = Jogo::factory()->create();
        $modelo = Jogo::factory()->create(['e_modelo' => true, 'nome_modelo' => 'Modelo', 'status' => StatusJogo::Agendado]);
        $this->get($rascunho->linkDoRelogio())->assertNotFound();
        $this->get($modelo->linkDoRelogio())->assertNotFound();
    }
}
