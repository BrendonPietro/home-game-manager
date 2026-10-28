<?php

namespace App\Filament\Resources\Grupos\Pages;

use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Grupo;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGrupo extends CreateRecord
{
    protected static string $resource = GrupoResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $grupo = Grupo::create(['name' => $data['name'], 'guard_name' => $data['guard_name']]);
        $grupo->syncPermissions($data['permissoes'] ?? []);

        return $grupo;
    }
}
