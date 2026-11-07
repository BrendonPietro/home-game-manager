<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\LoginResponse;
use App\Filament\Auth\Perfil;
use App\Http\Controllers\Painel\ImprimirRelatorio;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContrato;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Painel administrativo em /painel (docs/painel.md). Contas da tabela `seguranca`, guard `painel`.
 */
class PainelPanelProvider extends PanelProvider
{
    /**
     * Vermelho carta. O botão do Filament usa o tom 600 de fundo e o 500 no hover:
     * 600 = red-500, 500 = red-400, 700 = red-600 (pressionado), 400 = red-300 (texto no escuro).
     */
    private const VERMELHO = [
        50 => '#fdf2f2', 100 => '#fbe3e4', 200 => '#f7c6c8', 300 => '#f4a3a6',
        400 => '#f07a7f', 500 => '#d8434d', 600 => '#c62f3a', 700 => '#9e2530',
        800 => '#7a1d25', 900 => '#55141a', 950 => '#330b0f',
    ];

    /**
     * Grafite: 950 = bg-000 (página), 900 = bg-200 (painéis), 800 = bg-300, 700 = line-200,
     * 600 = line-control; 100 / 400 / 500 = ink-100 / ink-200 / ink-300.
     */
    private const GRAFITE = [
        50 => '#f7f5f1', 100 => '#eeeae3', 200 => '#dcd7cf', 300 => '#c8c2b9',
        400 => '#b4aea5', 500 => '#938d85', 600 => '#646c71', 700 => '#343a3e',
        800 => '#202427', 900 => '#181b1d', 950 => '#0b0c0d',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('painel')
            ->path('painel')
            ->authGuard('painel')
            ->login(Login::class)
            ->profile(Perfil::class, isSimple: false) // trocar a própria senha (menu do usuário → Perfil)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.painel.link-site'))
            ->brandName('Rodrigues Home Game')
            ->brandLogo(fn () => view('filament.painel.marca'))
            ->brandLogoHeight('1.75rem')
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('Barlow', provider: LocalFontProvider::class) // @font-face no tema; sem CDN
            ->colors([
                'primary' => self::VERMELHO,
                'danger' => self::VERMELHO,
                'gray' => self::GRAFITE,
                'success' => self::paleta('#5fcf8f'),
                'warning' => self::paleta('#e7b552'),
                'info' => self::paleta('#7fb2f0'),
            ])
            ->viteTheme('resources/css/filament/painel/theme.css')
            ->sidebarWidth('14.5rem') // 232px, como o AdminShell do design system
            // Módulos do menu: o Painel de controle fica no topo, sem grupo.
            ->navigationGroups(['Cadastros', 'Agenda', 'Dashboards e relatórios', 'Sistema'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages') // Dashboard (tela 16), Calendario
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // "Exportar PDF" dos relatórios: página para imprimir (filament.painel.relatorios.imprimir)
            ->authenticatedRoutes(fn () => Route::get('relatorios/imprimir', ImprimirRelatorio::class)->name('relatorios.imprimir'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function register(): void
    {
        parent::register();

        // Depois do login, só volta para página do painel (a sessão é a mesma do site).
        $this->app->bind(LoginResponseContrato::class, LoginResponse::class);
    }

    /**
     * Paleta gerada pelo matiz do token, com o próprio token no tom 400
     * (o que o Filament usa para texto e ícone no tema escuro).
     *
     * @return array<int, string>
     */
    private static function paleta(string $token): array
    {
        return array_replace(Color::generatePalette($token), [400 => $token]);
    }
}
