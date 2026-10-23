<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Tela 01: login do site (guard web, tabela usuarios)
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    // Tela 02: dashboard (por enquanto só a casca)
    Route::get('/', [HomeController::class, 'index'])->name('home');
});

// Vitrine do design system, só para desenvolvimento.
if (app()->environment('local')) {
    Route::view('/vitrine', 'site.pages.vitrine')->name('vitrine');
}
