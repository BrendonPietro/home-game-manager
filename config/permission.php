<?php

use App\Models\Grupo;
use App\Models\Permissao;
use Spatie\Permission\DefaultTeamResolver;

/*
| spatie/laravel-permission v6 com tabelas em português.
| Grupo = role, Permissão = permission. Sem teams.
*/

return [

    'models' => [
        'permission' => Permissao::class,
        'role' => Grupo::class,
    ],

    'table_names' => [
        'roles' => 'grupos',
        'permissions' => 'permissoes',
        'model_has_permissions' => 'modelo_permissoes',
        'model_has_roles' => 'modelo_grupos',
        'role_has_permissions' => 'grupo_permissoes',
    ],

    'column_names' => [
        'role_pivot_key' => null,       // role_id
        'permission_pivot_key' => null, // permission_id
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'register_permission_check_method' => true,

    'register_octane_reset_listener' => false,

    'events_enabled' => false,

    'teams' => false,

    'team_resolver' => DefaultTeamResolver::class,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
