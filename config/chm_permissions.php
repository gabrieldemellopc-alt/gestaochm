<?php

return [
    'profiles' => [
        'supervisor' => 'Supervisor',
    ],

    'modules' => [
        'fleet' => 'Frota',
    ],

    'managed_profiles' => [
        'supervisor',
    ],

    'groups' => [
        'navigation' => [
            'label' => 'Navegação',
            'description' => 'Define quais áreas aparecem e podem ser acessadas pelo perfil no ambiente operacional.',
            'permissions' => [
                'navigation.dashboard' => ['label' => 'Acessar Dashboard', 'default' => ['supervisor' => true]],
                'navigation.vehicles' => ['label' => 'Acessar Veículos', 'default' => ['supervisor' => true]],
                'navigation.workshop' => ['label' => 'Acessar Oficina', 'default' => ['supervisor' => true]],
                'navigation.fuel' => ['label' => 'Acessar Abastecimentos', 'default' => ['supervisor' => true]],
                'navigation.stock' => ['label' => 'Acessar Estoque', 'default' => ['supervisor' => true]],
                'navigation.tires' => ['label' => 'Acessar Pneus', 'default' => ['supervisor' => true]],
                'navigation.checklists' => ['label' => 'Acessar Checklists', 'default' => ['supervisor' => true]],
                'navigation.reports' => ['label' => 'Acessar Relatórios', 'default' => ['supervisor' => false]],
                'navigation.fiscal_documents' => ['label' => 'Acessar Notas Fiscais', 'default' => ['supervisor' => false]],
                'navigation.audit' => ['label' => 'Acessar Auditoria', 'default' => ['supervisor' => false]],
            ],
        ],

        'vehicles' => [
            'label' => 'Veículos',
            'description' => 'Permissões operacionais relacionadas ao cadastro e acompanhamento da frota.',
            'permissions' => [
                'vehicles.view' => ['label' => 'Ver veículos', 'default' => ['supervisor' => true]],
                'vehicles.create' => ['label' => 'Criar veículo', 'default' => ['supervisor' => false]],
                'vehicles.update' => ['label' => 'Editar veículo', 'default' => ['supervisor' => false]],
                'vehicles.update_km_hours' => ['label' => 'Atualizar KM/Horímetro', 'default' => ['supervisor' => true]],
                'vehicles.view_dossier' => ['label' => 'Ver dossiê do veículo', 'default' => ['supervisor' => false]],
            ],
        ],

        'maintenance' => [
            'label' => 'Manutenção/Oficina',
            'description' => 'Permissões para ordens, procedimentos, status e encerramentos de manutenção.',
            'permissions' => [
                'maintenance.view' => ['label' => 'Ver oficina e manutenções', 'default' => ['supervisor' => true]],
                'maintenance.open' => ['label' => 'Abrir manutenção', 'default' => ['supervisor' => true]],
                'maintenance.add_items' => ['label' => 'Adicionar procedimentos', 'default' => ['supervisor' => true]],
                'maintenance.consume_stock' => ['label' => 'Consumir estoque em manutenção', 'default' => ['supervisor' => true]],
                'maintenance.add_extra_costs' => ['label' => 'Lançar custos avulsos', 'default' => ['supervisor' => false]],
                'maintenance.change_status' => ['label' => 'Alterar status', 'default' => ['supervisor' => true]],
                'maintenance.close' => ['label' => 'Encerrar manutenção', 'default' => ['supervisor' => true]],
                'maintenance.cancel' => ['label' => 'Cancelar manutenção', 'default' => ['supervisor' => true]],
                'maintenance.reopen' => ['label' => 'Reabrir manutenção', 'default' => ['supervisor' => false]],
                'maintenance.delete' => ['label' => 'Excluir manutenção', 'default' => ['supervisor' => false]],
                'maintenance.view_costs' => ['label' => 'Ver custos de manutenção', 'default' => ['supervisor' => true]],
                'maintenance.export_pdf' => ['label' => 'Exportar PDF da ordem', 'default' => ['supervisor' => true]],
                'maintenance.view_cancellation_details' => ['label' => 'Ver detalhes de cancelamento', 'default' => ['supervisor' => false]],
            ],
        ],

        'fuel' => [
            'label' => 'Abastecimentos',
            'description' => 'Permissões para recebimentos, abastecimentos internos/externos e custos de combustível.',
            'permissions' => [
                'fuel.view' => ['label' => 'Ver abastecimentos', 'default' => ['supervisor' => true]],
                'fuel.receive' => ['label' => 'Receber combustível no tanque', 'default' => ['supervisor' => true]],
                'fuel.fill_internal' => ['label' => 'Lançar abastecimento interno', 'default' => ['supervisor' => true]],
                'fuel.fill_external' => ['label' => 'Lançar abastecimento externo', 'default' => ['supervisor' => true]],
                'fuel.cancel' => ['label' => 'Cancelar abastecimento', 'default' => ['supervisor' => false]],
                'fuel.view_costs' => ['label' => 'Ver custos de combustível', 'default' => ['supervisor' => true]],
            ],
        ],

        'stock' => [
            'label' => 'Estoque',
            'description' => 'Permissões para movimentações, consumo e visualização de custos de estoque.',
            'permissions' => [
                'stock.view' => ['label' => 'Ver estoque', 'default' => ['supervisor' => true]],
                'stock.manage_categories' => ['label' => 'Gerenciar categorias', 'default' => ['supervisor' => false]],
                'stock.manage_items' => ['label' => 'Gerenciar itens', 'default' => ['supervisor' => false]],
                'stock.entry' => ['label' => 'Registrar entrada', 'default' => ['supervisor' => true]],
                'stock.manual_output' => ['label' => 'Registrar saída manual', 'default' => ['supervisor' => true]],
                'stock.consume_maintenance' => ['label' => 'Consumir em manutenção', 'default' => ['supervisor' => true]],
                'stock.cancel_movement' => ['label' => 'Cancelar movimentação', 'default' => ['supervisor' => true]],
                'stock.view_costs' => ['label' => 'Ver custos de estoque', 'default' => ['supervisor' => true]],
            ],
        ],

        'tires' => [
            'label' => 'Pneus',
            'description' => 'Permissões para entrada, instalação, medição, recapagem e cancelamentos de pneus.',
            'permissions' => [
                'tires.view' => ['label' => 'Ver pneus', 'default' => ['supervisor' => true]],
                'tires.entry' => ['label' => 'Registrar entrada', 'default' => ['supervisor' => true]],
                'tires.manage_inventory' => ['label' => 'Gerenciar cadastro/inventario', 'default' => ['supervisor' => false]],
                'tires.install' => ['label' => 'Instalar pneu', 'default' => ['supervisor' => true]],
                'tires.measure' => ['label' => 'Registrar medição', 'default' => ['supervisor' => true]],
                'tires.remove' => ['label' => 'Retirar pneu do veiculo', 'default' => ['supervisor' => true]],
                'tires.retread' => ['label' => 'Registrar recapagem', 'default' => ['supervisor' => true]],
                'tires.cancel' => ['label' => 'Cancelar registros de pneus', 'default' => ['supervisor' => true]],
                'tires.view_costs' => ['label' => 'Ver custos de pneus', 'default' => ['supervisor' => true]],
            ],
        ],

        'reports_documents' => [
            'label' => 'Relatórios e documentos',
            'description' => 'Permissões para consultas gerenciais, exportações e documentos consolidados.',
            'permissions' => [
                'reports.view' => ['label' => 'Acessar Central de Relatórios', 'default' => ['supervisor' => false]],
                'reports.view_operational' => ['label' => 'Ver relatÃ³rios operacionais', 'default' => ['supervisor' => false]],
                'reports.vehicle_dossier' => ['label' => 'Ver dossiê do veículo', 'default' => ['supervisor' => false]],
                'reports.maintenance' => ['label' => 'Ver relatório de manutenções', 'default' => ['supervisor' => false]],
                'reports.fuel' => ['label' => 'Ver relatório de abastecimentos', 'default' => ['supervisor' => false]],
                'reports.stock' => ['label' => 'Ver relatório de estoque', 'default' => ['supervisor' => false]],
                'reports.tires' => ['label' => 'Ver relatório de pneus', 'default' => ['supervisor' => false]],
                'reports.export_pdf' => ['label' => 'Exportar PDF', 'default' => ['supervisor' => false]],
                'reports.export_excel' => ['label' => 'Exportar Excel', 'default' => ['supervisor' => false]],
                'reports.view_costs' => ['label' => 'Ver custos nos relatórios', 'default' => ['supervisor' => false]],
                'fiscal_documents.view' => ['label' => 'Ver notas fiscais', 'default' => ['supervisor' => false]],
                'fiscal_documents.view_details' => ['label' => 'Ver detalhes de notas fiscais', 'default' => ['supervisor' => false]],
                'fiscal_documents.open_origin' => ['label' => 'Abrir origem da nota fiscal', 'default' => ['supervisor' => false]],
                'fiscal_documents.view_values' => ['label' => 'Ver valores de notas fiscais', 'default' => ['supervisor' => false]],
                'audit.view' => ['label' => 'Ver auditoria', 'default' => ['supervisor' => false]],
                'audit.view_details' => ['label' => 'Ver detalhes da auditoria', 'default' => ['supervisor' => false]],
                'audit.view_technical_details' => ['label' => 'Ver detalhes técnicos da auditoria', 'default' => ['supervisor' => false]],

            ],
        ],

        'administration' => [
            'label' => 'Administração',
            'description' => 'Permissões administrativas sensíveis. Neste bloco são apenas catálogo/preparação.',
            'permissions' => [
                'admin.users.manage' => ['label' => 'Gerenciar usuários', 'default' => ['supervisor' => false]],
                'admin.access.manage' => ['label' => 'Gerenciar acessos', 'default' => ['supervisor' => false]],
                'admin.permissions.configure' => ['label' => 'Configurar permissões', 'default' => ['supervisor' => false]],
            ],
        ],
    ],
];