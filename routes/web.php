<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JogoController;
use App\Http\Controllers\RelogioPublicoController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Tela 01: login do site (guard web, tabela usuarios)
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

// site.acesso: conta desativada sai na hora; quem não é admin só vê o torneio dentro da janela dele
// (3 h antes do início a 3 h depois do término previsto, App\Support\JanelaDoSite).
Route::middleware(['auth:web', 'site.acesso'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    // Fora da janela: continua logado, sem nada do torneio, e diz quando o próximo abre.
    Route::get('/sem-torneio', [HomeController::class, 'semTorneio'])->name('sem-torneio');

    // Tela 02: dashboard
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Tela 03: escolher o jogo
    Route::get('/jogos', [JogoController::class, 'index'])->name('jogos.index');

    // Antes da noite (jogo agendado). jogo.etapa manda para a etapa certa quando o status não permite.
    Route::get('/jogos/{jogo}/conferencia', [JogoController::class, 'conferencia'])
        ->middleware('jogo.etapa:conferencia')
        ->name('jogos.conferencia');
    Route::get('/jogos/{jogo}/jogadores', [JogoController::class, 'jogadores'])
        ->middleware('jogo.etapa:jogadores')
        ->name('jogos.jogadores');
    Route::get('/jogos/{jogo}/mesas', [JogoController::class, 'mesas'])
        ->middleware('jogo.etapa:mesas')
        ->name('jogos.mesas');

    // Durante a noite (jogo em andamento).
    Route::get('/jogos/{jogo}/ao-vivo', [JogoController::class, 'aoVivo'])
        ->middleware('jogo.etapa:ao-vivo')
        ->name('jogos.ao-vivo');
    Route::get('/jogos/{jogo}/resumo', [JogoController::class, 'resumo'])
        ->middleware('jogo.etapa:resumo')
        ->name('jogos.resumo');

    // v3: tela do relógio de quem controla o torneio (botões grandes e o AVISO).
    Route::get('/jogos/{jogo}/relogio', [JogoController::class, 'relogio'])
        ->middleware('jogo.etapa:ao-vivo')
        ->name('jogos.relogio');

    // v3: a TV é o link do relógio do torneio (só o relógio); o endereço antigo leva para ele.
    Route::get('/jogos/{jogo}/tv', [JogoController::class, 'tv'])->name('jogos.tv');

    // Final da noite: abre com as entradas fechadas (cash game: a qualquer hora) e depois de finalizar.
    Route::get('/jogos/{jogo}/premiacao', [JogoController::class, 'premiacao'])
        ->middleware('jogo.etapa:premiacao')
        ->name('jogos.premiacao');
    Route::get('/jogos/{jogo}/resultado', [JogoController::class, 'resultado'])
        ->middleware('jogo.etapa:resultado')
        ->name('jogos.resultado');
    Route::get('/jogos/{jogo}/fechamento', [JogoController::class, 'fechamento'])
        ->middleware('jogo.etapa:fechamento')
        ->name('jogos.fechamento');
});

// v3: relógio do torneio para outras telas (TV, tablet, celular), sem login e só para ver. O estado
// vem sem sessão, cookie nem CSRF: a tela pede a cada 2 s e nunca "expira", nem depois de horas sem rede.
Route::get('/relogio/{codigo}', [RelogioPublicoController::class, 'mostrar'])->name('relogio.publico');
Route::get('/relogio/{codigo}/estado', [RelogioPublicoController::class, 'estado'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('relogio.estado');

// Vitrine do design system, só para desenvolvimento.
if (app()->environment('local')) {
    Route::view('/vitrine', 'site.pages.vitrine')->name('vitrine');
}
