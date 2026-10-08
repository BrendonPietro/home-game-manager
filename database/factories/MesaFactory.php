<?php

namespace Database\Factories;

use App\Models\Mesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mesa> */
class MesaFactory extends Factory
{
    protected $model = Mesa::class;

    public function definition(): array
    {
        return [
            'nome' => 'Mesa '.fake()->unique()->numberBetween(1, 99),
            'lugares' => 9,
            'ativa' => true,
        ];
    }
}
