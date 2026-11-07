<?php

namespace Database\Factories;

use App\Http\Middleware\AcessoDoSite;
use App\Models\Permissao;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Usuario> */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    protected static ?string $senha;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'apelido' => fake()->optional()->firstName(),
            'telefone' => fake()->optional()->numerify('(11) 9####-####'),
            'acessa_site' => false,
            'pode_ser_dealer' => false,
            'pode_ser_floor' => false,
            'ativo' => true,
        ];
    }

    /** Quem opera o site (tem login). */
    public function comAcesso(): static
    {
        return $this->state(fn () => [
            'acessa_site' => true,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$senha ??= Hash::make('password'),
        ]);
    }

    /** Usa o site também sem jogo em andamento (`site.fora_da_noite`, como o Admin do site). */
    public function foraDaNoite(): static
    {
        return $this->comAcesso()->afterCreating(function (Usuario $usuario): void {
            Permissao::findOrCreate(AcessoDoSite::FORA_DA_NOITE, 'web');
            $usuario->givePermissionTo(AcessoDoSite::FORA_DA_NOITE);
        });
    }

    public function dealer(): static
    {
        return $this->state(fn () => ['pode_ser_dealer' => true]);
    }

    public function floor(): static
    {
        return $this->state(fn () => ['pode_ser_floor' => true]);
    }
}
