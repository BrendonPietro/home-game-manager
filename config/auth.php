<?php

use App\Models\Seguranca;
use App\Models\Usuario;

/*
| Dois logins:
| - web    → tabela `usuarios` (site de operação em /)
| - painel → tabela `seguranca` (Filament em /painel, ->authGuard('painel'))
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'usuarios'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],

        'painel' => [
            'driver' => 'session',
            'provider' => 'seguranca',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => Usuario::class,
        ],

        'seguranca' => [
            'driver' => 'eloquent',
            'model' => Seguranca::class,
        ],
    ],

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
