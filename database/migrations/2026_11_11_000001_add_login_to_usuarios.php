<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login do site por nome de usuário (ex.: "fulanodetal"), além do e-mail. Gravado em minúsculas;
 * sem @, para o login saber se a pessoa digitou o usuário ou o e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('login', 60)->nullable()->unique()->after('acessa_site');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }
};
