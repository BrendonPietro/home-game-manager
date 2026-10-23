<?php

namespace App\Providers;

use App\Models\Seguranca;
use BladeUI\Icons\Factory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ícones do design system: @svg('rh-home') ou <x-rh-home />
        $this->callAfterResolving(Factory::class, function (Factory $icones) {
            $icones->add('rh', [
                'path' => resource_path('svg/site/icons'),
                'prefix' => 'rh',
                'class' => 'rh-ic',
                'attributes' => ['aria-hidden' => 'true'],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contas do painel: registra o último acesso (inclusive login por "lembrar acesso"),
        // sem mexer no updated_at da conta.
        Event::listen(function (Login $evento) {
            if ($evento->guard === 'painel' && $evento->user instanceof Seguranca) {
                Seguranca::withoutTimestamps(
                    fn () => $evento->user->forceFill(['ultimo_acesso' => now()])->saveQuietly(),
                );
            }
        });
    }
}
