<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JogoController;
use Illuminate\Support\Facades\Route;

// Tela 01: login do site (guard web, tabela usuarios)
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

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
});

// Vitrine do design system, só para desenvolvimento.
if (app()->environment('local')) {
    Route::view('/vitrine', 'site.pages.vitrine')->name('vitrine');
}
