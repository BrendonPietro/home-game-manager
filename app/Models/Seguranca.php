<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Contas do painel /painel (guard `painel`). Login por `login`, não por e-mail.
 */
class Seguranca extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\SegurancaFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'seguranca';

    protected $guard_name = 'painel';

    protected $fillable = [
        'nome',
        'login',
        'password',
        'ativo',
        'ultimo_acesso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
            'ultimo_acesso' => 'datetime',
        ];
    }
}
