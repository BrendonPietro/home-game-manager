import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Site de operação (SCSS do design system + Livewire/Alpine)
                'resources/scss/site/index.scss',
                'resources/js/site/app.js',
                // Tema do painel Filament (Tailwind 4)
                'resources/css/filament/painel/theme.css',
            ],
            refresh: true,
        }),
        // Só o tema do painel usa Tailwind; o site não.
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
