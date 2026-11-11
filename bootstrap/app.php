<?php

use App\Http\Middleware\AcessoDoSite;
use App\Http\Middleware\JogoEtapa;
use App\Support\PaginaQueNaoExiste;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// PHP dentro do Apache (Laragon no Windows): o ambiente do processo é um só para todos os sites, e o
// .env de outro projeto atendendo ao mesmo tempo vazava para cá (outro banco, outra chave, a sessão
// sumia e caía no login). Aqui o .env vai só para $_SERVER e $_ENV, que são de cada pedido.
if (PHP_SAPI === 'apache2handler') {
    Env::disablePutenv();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jogo.etapa' => JogoEtapa::class,
            'site.acesso' => AcessoDoSite::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Página que não existe (jogo excluído, link antigo) volta para o início, com um aviso, em vez do 404.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => PaginaQueNaoExiste::redirecionar($request));
    })->create();
