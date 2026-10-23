<?php

namespace Tests\Concerns;

use App\Models\Seguranca;
use Database\Seeders\PermissoesSeeder;
use Filament\Facades\Filament;

/**
 * Entra no painel com uma conta de um grupo (Desenvolvedor, Admin, Geral), com as permissões do seed.
 * Usar junto com RefreshDatabase.
 */
trait UsaPainel
{
    protected function entrarNoPainel(string $grupo = 'Admin'): Seguranca
    {
        $this->seed(PermissoesSeeder::class);
        $this->withoutVite();
        Filament::setCurrentPanel('painel');

        $conta = Seguranca::factory()->create();
        $conta->assignRole($grupo);

        $this->actingAs($conta, 'painel');

        return $conta;
    }
}
