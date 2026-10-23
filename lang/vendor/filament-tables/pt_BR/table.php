<?php

// Chaves que faltam na tradução pt_BR do Filament 5 (o Laravel junta com o arquivo do pacote).

return [

    'column_manager' => [
        'position' => 'Posição :position de :count.',
        'position_in_group' => 'Posição :position de :count em :group.',
        'reordered' => ':column movida para a posição :position de :count.',
        'reordered_deferred' => ':column movida para a posição :position de :count. Aplique as colunas para salvar.',
        'reordered_in_group' => ':column movida para a posição :position de :count em :group.',
        'reordered_in_group_deferred' => ':column movida para a posição :position de :count em :group. Aplique as colunas para salvar.',
        'actions' => [
            'move_up' => ['label' => 'Mover :column para cima'],
            'move_down' => ['label' => 'Mover :column para baixo'],
        ],
    ],

    'columns' => [
        'editable' => [
            'saving' => 'Salvando…',
            'saved' => 'Alteração salva.',
            'failure' => 'Não foi possível confirmar o salvamento. Recarregue a página antes de tentar de novo.',
            'unavailable' => 'Este item não pode mais ser editado.',
        ],
    ],

    'actions' => [
        'move_record_up' => ['label' => 'Mover :label para cima'],
        'move_record_down' => ['label' => 'Mover :label para baixo'],
    ],

    'reordering' => [
        'position' => ':label, posição :position de :count.',
        'moved' => ':label movido para a posição :position de :count.',
    ],

];
