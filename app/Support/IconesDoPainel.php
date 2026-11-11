<?php

namespace App\Support;

/**
 * Ícones do próprio Filament (ações, tabela, menu, formulários, avisos…) apontados para os arquivos de
 * resources/svg/icones, para o painel inteiro trocar de ícone junto com o site: trocou o arquivo, trocou
 * em todo lugar que usa aquela ideia (ex.: excluir.svg vale para todo botão de excluir, no painel e no site).
 *
 * Ficam de fora só os apelidos de coisas que o painel não usa (editor de texto rico, autenticação em dois
 * fatores, multi-empresa, editor de imagem do upload). Registrado no AppServiceProvider (FilamentIcon::register).
 */
class IconesDoPainel
{
    /** @return array<string, string> apelido do Filament => ícone rh-… */
    public static function apelidos(): array
    {
        $icones = [
            // Ações
            'actions::action-group' => 'mais-opcoes',
            'actions::create-action.grouped' => 'adicionar',
            'actions::delete-action' => 'excluir',
            'actions::delete-action.grouped' => 'excluir',
            'actions::delete-action.modal' => 'excluir',
            'actions::detach-action' => 'excluir',
            'actions::detach-action.modal' => 'excluir',
            'actions::dissociate-action' => 'excluir',
            'actions::dissociate-action.modal' => 'excluir',
            'actions::edit-action' => 'editar',
            'actions::edit-action.grouped' => 'editar',
            'actions::export-action.grouped' => 'baixar',
            'actions::force-delete-action' => 'excluir',
            'actions::force-delete-action.grouped' => 'excluir',
            'actions::force-delete-action.modal' => 'excluir',
            'actions::import-action.grouped' => 'enviar',
            'actions::modal.confirmation' => 'alerta',
            'actions::replicate-action' => 'duplicar',
            'actions::replicate-action.grouped' => 'duplicar',
            'actions::restore-action' => 'desfazer',
            'actions::restore-action.grouped' => 'desfazer',
            'actions::restore-action.modal' => 'desfazer',
            'actions::view-action' => 'ver',
            'actions::view-action.grouped' => 'ver',

            // Painel: menu, topo, conta, tema
            'panels::global-search.field' => 'pesquisar',
            'panels::pages.dashboard.actions.filter' => 'filtrar',
            'panels::pages.dashboard.navigation-item' => 'menu-painel-de-controle',
            'panels::resources.pages.edit-record.navigation-item' => 'editar',
            'panels::resources.pages.manage-related-records.navigation-item' => 'lista',
            'panels::resources.pages.view-record.navigation-item' => 'ver',
            'panels::sidebar.collapse-button' => 'chevron-esquerda',
            'panels::sidebar.collapse-button.rtl' => 'chevron-direita',
            'panels::sidebar.expand-button' => 'chevron-direita',
            'panels::sidebar.expand-button.rtl' => 'chevron-esquerda',
            'panels::sidebar.group.collapse-button' => 'chevron-cima',
            'panels::sub-navigation.mobile-menu.button' => 'menu',
            'panels::theme-switcher.light-button' => 'tema-claro',
            'panels::theme-switcher.dark-button' => 'tema-escuro',
            'panels::theme-switcher.system-button' => 'tema-do-sistema',
            'panels::topbar.close-sidebar-button' => 'fechar',
            'panels::topbar.open-sidebar-button' => 'menu',
            'panels::topbar.group.toggle-button' => 'chevron-baixo',
            'panels::topbar.open-database-notifications-button' => 'notificacoes',
            'panels::sidebar.open-database-notifications-button' => 'notificacoes',
            'panels::user-menu.profile-item' => 'perfil',
            'panels::user-menu.logout-button' => 'sair',
            'panels::widgets.account.logout-button' => 'sair',
            'panels::pages.password-reset.request-password-reset.actions.login' => 'seta-direita',

            // Formulários
            'forms::components.builder.actions.clone' => 'duplicar',
            'forms::components.builder.actions.collapse' => 'chevron-cima',
            'forms::components.builder.actions.delete' => 'excluir',
            'forms::components.builder.actions.edit' => 'editar',
            'forms::components.builder.actions.expand' => 'chevron-baixo',
            'forms::components.builder.actions.move-down' => 'seta-baixo',
            'forms::components.builder.actions.move-up' => 'seta-cima',
            'forms::components.builder.actions.reorder' => 'reordenar',
            'forms::components.checkbox-list.search-field' => 'pesquisar',
            'forms::components.key-value.actions.delete' => 'excluir',
            'forms::components.key-value.actions.reorder' => 'reordenar',
            'forms::components.repeater.actions.clone' => 'duplicar',
            'forms::components.repeater.actions.collapse' => 'chevron-cima',
            'forms::components.repeater.actions.delete' => 'excluir',
            'forms::components.repeater.actions.expand' => 'chevron-baixo',
            'forms::components.repeater.actions.move-down' => 'seta-baixo',
            'forms::components.repeater.actions.move-up' => 'seta-cima',
            'forms::components.repeater.actions.reorder' => 'reordenar',
            'forms::components.select.actions.create-option' => 'adicionar',
            'forms::components.select.actions.edit-option' => 'editar',
            'forms::components.text-input.actions.copy' => 'copiar',
            'forms::components.text-input.actions.hide-password' => 'esconder-senha',
            'forms::components.text-input.actions.show-password' => 'ver',
            'forms::components.toggle-buttons.boolean.false' => 'fechar',
            'forms::components.toggle-buttons.boolean.true' => 'check',

            // Sim/não em colunas e campos de leitura
            'infolists::components.icon-entry.false' => 'fechar',
            'infolists::components.icon-entry.true' => 'check',
            'tables::columns.icon-column.false' => 'fechar',
            'tables::columns.icon-column.true' => 'check',

            // Avisos
            'notifications::database.modal.empty-state' => 'notificacoes',
            'notifications::notification.close-button' => 'fechar',
            'notifications::notification.danger' => 'erro',
            'notifications::notification.info' => 'info',
            'notifications::notification.success' => 'sucesso',
            'notifications::notification.warning' => 'alerta',
            'schema::components.callout.danger' => 'erro',
            'schema::components.callout.info' => 'info',
            'schema::components.callout.success' => 'sucesso',
            'schema::components.callout.warning' => 'alerta',
            'schema::components.tabs.dropdown-trigger-button' => 'chevron-baixo',
            'schema::components.tabs.more-tabs-button' => 'chevron-baixo',
            'schema::components.wizard.completed-step' => 'check',

            // Comuns: modal, migalhas, paginação, seções
            'badge.delete-button' => 'fechar',
            'breadcrumbs.separator' => 'chevron-direita',
            'breadcrumbs.separator.rtl' => 'chevron-esquerda',
            'modal.close-button' => 'fechar',
            'pagination.first-button' => 'chevron-duplo-esquerda',
            'pagination.first-button.rtl' => 'chevron-duplo-direita',
            'pagination.last-button' => 'chevron-duplo-direita',
            'pagination.last-button.rtl' => 'chevron-duplo-esquerda',
            'pagination.next-button' => 'chevron-direita',
            'pagination.next-button.rtl' => 'chevron-esquerda',
            'pagination.previous-button' => 'chevron-esquerda',
            'pagination.previous-button.rtl' => 'chevron-direita',
            'section.collapse-button' => 'chevron-cima',

            // Tabelas
            'tables::actions.disable-reordering' => 'check',
            'tables::actions.enable-reordering' => 'reordenar',
            'tables::actions.filter' => 'filtrar',
            'tables::actions.group' => 'agrupar',
            'tables::actions.open-bulk-actions' => 'mais-opcoes',
            'tables::actions.column-manager' => 'colunas',
            'tables::columns.collapse-button' => 'chevron-baixo',
            'tables::empty-state' => 'vazio',
            'tables::filters.remove-all-button' => 'fechar',
            'tables::grouping.collapse-button' => 'chevron-cima',
            'tables::header-cell.sort-asc-button' => 'chevron-cima',
            'tables::header-cell.sort-button' => 'chevron-cima-baixo',
            'tables::header-cell.sort-desc-button' => 'chevron-baixo',
            'tables::reorder.handle' => 'reordenar',
            'tables::search-field' => 'pesquisar',

            // Gráficos (widgets)
            'widgets::chart-widget.filter' => 'filtrar',
            'widgets::chart-widget.empty-state' => 'vazio',
        ];

        return array_map(fn (string $nome): string => 'rh-'.$nome, $icones);
    }
}
