<?php

namespace Database\Factories;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Seguranca> */
class SegurancaFactory extends Factory
{
    protected $model = Seguranca::class;

    protected static ?string $senha;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'login' => fake()->unique()->userName(),
            'password' => static::$senha ??= Hash::make('password'),
            'ativo' => true,
        ];
    }
}
