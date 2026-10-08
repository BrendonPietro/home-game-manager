<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do spatie/laravel-permission v6, com os nomes de config/permission.php:
 * grupos, permissoes, grupo_permissoes, modelo_grupos, modelo_permissoes.
 * Sem teams. `guard_name` separa painel (`painel`) e site (`web`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tabelas = config('permission.table_names');
        $colunas = config('permission.column_names');
        $pivotGrupo = $colunas['role_pivot_key'] ?? 'role_id';
        $pivotPermissao = $colunas['permission_pivot_key'] ?? 'permission_id';
        $morph = $colunas['model_morph_key'] ?? 'model_id';

        throw_if(empty($tabelas), new Exception('config/permission.php não carregado. Rode php artisan config:clear.'));

        Schema::create($tabelas['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tabelas['roles'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tabelas['model_has_permissions'], function (Blueprint $table) use ($tabelas, $pivotPermissao, $morph) {
            $table->unsignedBigInteger($pivotPermissao);
            $table->string('model_type');
            $table->unsignedBigInteger($morph);
            $table->index([$morph, 'model_type'], 'modelo_permissoes_model_id_model_type_index');

            $table->foreign($pivotPermissao)->references('id')->on($tabelas['permissions'])->cascadeOnDelete();
            $table->primary([$pivotPermissao, $morph, 'model_type'], 'modelo_permissoes_primary');
        });

        Schema::create($tabelas['model_has_roles'], function (Blueprint $table) use ($tabelas, $pivotGrupo, $morph) {
            $table->unsignedBigInteger($pivotGrupo);
            $table->string('model_type');
            $table->unsignedBigInteger($morph);
            $table->index([$morph, 'model_type'], 'modelo_grupos_model_id_model_type_index');

            $table->foreign($pivotGrupo)->references('id')->on($tabelas['roles'])->cascadeOnDelete();
            $table->primary([$pivotGrupo, $morph, 'model_type'], 'modelo_grupos_primary');
        });

        Schema::create($tabelas['role_has_permissions'], function (Blueprint $table) use ($tabelas, $pivotGrupo, $pivotPermissao) {
            $table->unsignedBigInteger($pivotPermissao);
            $table->unsignedBigInteger($pivotGrupo);

            $table->foreign($pivotPermissao)->references('id')->on($tabelas['permissions'])->cascadeOnDelete();
            $table->foreign($pivotGrupo)->references('id')->on($tabelas['roles'])->cascadeOnDelete();
            $table->primary([$pivotPermissao, $pivotGrupo], 'grupo_permissoes_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tabelas = config('permission.table_names');

        Schema::dropIfExists($tabelas['role_has_permissions']);
        Schema::dropIfExists($tabelas['model_has_roles']);
        Schema::dropIfExists($tabelas['model_has_permissions']);
        Schema::dropIfExists($tabelas['roles']);
        Schema::dropIfExists($tabelas['permissions']);
    }
};
