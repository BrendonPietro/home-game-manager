<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Chave/valor do clube (nome, logo, cor, padrões). Lida com cache.
 *
 *   Configuracao::valor('clube.nome', 'Rodrigues Home Game');
 *   Configuracao::definir('clube.nome', 'Rodrigues Home Game');
 */
class Configuracao extends Model
{
    public const CACHE = 'configuracoes';

    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE));
        static::deleted(fn () => Cache::forget(self::CACHE));
    }

    /** @return array<string, mixed> */
    public static function todas(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => static::query()->pluck('valor', 'chave')->all());
    }

    public static function valor(string $chave, mixed $padrao = null): mixed
    {
        return static::todas()[$chave] ?? $padrao;
    }

    public static function definir(string $chave, mixed $valor): void
    {
        static::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }
}
