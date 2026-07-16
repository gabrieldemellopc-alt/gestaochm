<?php

return [
    'maintenance_type' => [
        'preventive' => 'Preventiva',
        'corrective' => 'Corretiva',
        'internal' => 'Interna',
        'external' => 'Externa',
    ],

    'service_type' => [
        'internal' => 'Interna',
        'external' => 'Externa',
    ],

    'execution_type' => [
        'internal' => 'Interna',
        'external' => 'Externa',
    ],

    'workflow_status' => [
        'open' => 'Aberta',
        'closed' => 'Fechada',
        'cancelled' => 'Cancelada',
    ],

    'vehicle_status' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'unavailable' => 'Indisponível',
    ],

    'operational_status' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'operational' => 'Operacional',
        'maintenance' => 'Em manutenção',
        'out_of_service' => 'Fora de operação',
        'unavailable' => 'Indisponível',
    ],

    'service_status' => [
        'waiting_parts' => 'Aguardando peças',
        'in_progress' => 'Em andamento',
        'waiting_approval' => 'Aguardando aprovação',
        'completed' => 'Concluída',
        'cancelled' => 'Cancelada',
    ],

    'stock_movement' => [
        'reversal' => 'Reversão',
        'reverted' => 'Revertido',
        'entry' => 'Entrada',
        'output' => 'Saída',
        'receipt' => 'Recebimento',
        'consumption' => 'Consumo',
        'adjustment' => 'Ajuste',
    ],

    'fuel_movement' => [
        'receipt' => 'Recebimento',
        'filling' => 'Abastecimento',
        'adjustment' => 'Ajuste',
        'cancellation' => 'Cancelamento',
    ],

    'fuel_consumption_status' => [
        'calculado' => 'Calculado',
        'dados_insuficientes' => 'Dados insuficientes',
        'km_invalido' => 'KM inválido',
        'horas_invalidas' => 'Horas inválidas',
        'sem_km_hr' => 'Sem KM/HR',
    ],

    'audit_action' => [
        'created' => 'Criado',
        'updated' => 'Atualizado',
        'deleted' => 'Excluído',
        'cancelled' => 'Cancelado',
        'restored' => 'Restaurado',
    ],

    'tire_event' => [
        'installation' => 'Instalação',
        'removal' => 'Retirada',
        'measurement' => 'Medição',
        'retread' => 'Recapagem',
        'discard' => 'Descarte',
    ],
];