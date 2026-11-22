<?php

// Mensagens para quem desenvolve, português (LUANY_DEV_LANGUAGE=pt).
// Os códigos são os mesmos de en.php e nunca mudam.
return [
    'LUANY-CLI-001' => ':message',
    'LUANY-CLI-002' => 'O comando [:command] ainda não suporta --json.',
    'LUANY-CLI-003' => 'O comando [:command] ainda não suporta --dry-run; nada foi feito.',
    'LUANY-CLI-004' => 'Comando [:command] não encontrado.',
    'LUANY-CLI-005' => 'Utilização: luany :usage',

    'LUANY-MAKE-002' => ':name já existe (:path).',
    'LUANY-MAKE-003' => ':created: :path',
    'LUANY-MAKE-004' => 'Seria criado :path (simulação, nada foi escrito)',
    'LUANY-MAKE-005' => 'Não foi possível escrever :path.',
    'LUANY-MAKE-006' => 'A criar a funcionalidade [:name]…',
    'LUANY-MAKE-007' => 'Ainda nenhuma migração cria [:table] — crie-a antes de correr migrate (chave estrangeira).',
    'LUANY-MAKE-008' => 'A migração já existe — ignorada: :path',
    'LUANY-MAKE-009' => 'Rotas disponíveis:',

    'LUANY-ROUTE-001' => 'vendor/autoload.php não encontrado.',
    'LUANY-ROUTE-002' => 'Luany\\Core\\Routing\\Route não encontrado no vendor.',
    'LUANY-ROUTE-003' => 'Não há pasta routes.',
    'LUANY-ROUTE-004' => 'Nenhuma rota registada.',
    'LUANY-ROUTE-005' => ':count rota(s) registada(s).',

    'LUANY-VIEW-001' => 'O view:check precisa do luany/lte >= 1.2 neste projeto.',
    'LUANY-VIEW-002' => 'Pasta de vistas não encontrada: :path',
    'LUANY-VIEW-003' => ':errors de :count vista(s) com erros.',
    'LUANY-VIEW-004' => 'A verificar :count vista(s) em :path/',
    'LUANY-VIEW-005' => ':count vista(s) sem erros.',

    'LUANY-CLI-006' => 'Seria criado :path',
    'LUANY-CLI-007' => 'Seria alterado :path',
    'LUANY-CLI-008' => 'Seria apagado :path',

    'LUANY-KEY-001' => '.env.example não encontrado.',
    'LUANY-KEY-002' => '.env criado a partir do .env.example',
    'LUANY-KEY-003' => 'Chave da aplicação definida.',

    'LUANY-CACHE-001' => 'Cache limpa (:count ficheiro(s) removido(s)).',
    'LUANY-CACHE-002' => ':count vista(s) compilada(s) seria(m) removida(s) (simulação).',

    'LUANY-ROUTE-006' => 'Cache de rotas apagada.',
    'LUANY-ROUTE-007' => 'Não há cache de rotas para apagar.',

    'LUANY-MIGRATE-001' => 'Nenhuma migração encontrada.',

    'kind.controller.created' => 'Controlador criado',
    'kind.model.created'      => 'Modelo criado',
    'kind.middleware.created' => 'Middleware criado',
    'kind.provider.created'   => 'Provedor criado',
    'kind.request.created'    => 'Requisição criada',
    'kind.seeder.created'     => 'Seeder criado',
    'kind.test.created'       => 'Teste criado',
    'kind.view.created'       => 'Vista criada',
    'kind.routes.created'     => 'Rotas criadas',
    'kind.migration.created'  => 'Migração criada',
];
