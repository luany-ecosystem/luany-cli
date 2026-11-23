<?php

// Messages for developers, English. Codes never change; see pt.php.
return [
    'LUANY-CLI-001' => ':message',
    'LUANY-CLI-002' => 'The command [:command] does not support --json yet.',
    'LUANY-CLI-003' => 'The command [:command] does not support --dry-run yet; nothing was done.',
    'LUANY-CLI-004' => 'Command [:command] not found.',
    'LUANY-CLI-005' => 'Usage: luany :usage',

    'LUANY-MAKE-002' => ':name already exists (:path).',
    'LUANY-MAKE-003' => ':created: :path',
    'LUANY-MAKE-004' => 'Would create :path (dry run, nothing written)',
    'LUANY-MAKE-005' => 'Could not write :path.',
    'LUANY-MAKE-006' => 'Creating feature [:name]…',
    'LUANY-MAKE-007' => 'No migration creates [:table] yet — create it before running migrate (foreign key).',
    'LUANY-MAKE-008' => 'Migration already exists — skipped: :path',
    'LUANY-MAKE-009' => 'Routes available:',

    'LUANY-ROUTE-001' => 'vendor/autoload.php not found.',
    'LUANY-ROUTE-002' => 'Luany\\Core\\Routing\\Route not found in vendor.',
    'LUANY-ROUTE-003' => 'No routes directory found.',
    'LUANY-ROUTE-004' => 'No routes registered.',
    'LUANY-ROUTE-005' => ':count route(s) registered.',

    'LUANY-VIEW-001' => 'view:check needs luany/lte >= 1.2 in this project.',
    'LUANY-VIEW-002' => 'Views directory not found: :path',
    'LUANY-VIEW-003' => ':errors of :count view(s) have errors.',
    'LUANY-VIEW-004' => 'Checking :count view(s) in :path/',
    'LUANY-VIEW-005' => ':count view(s) OK.',

    'LUANY-CLI-006' => 'Would create :path',
    'LUANY-CLI-007' => 'Would update :path',
    'LUANY-CLI-008' => 'Would delete :path',

    'LUANY-KEY-001' => '.env.example not found.',
    'LUANY-KEY-002' => '.env created from .env.example',
    'LUANY-KEY-003' => 'Application key set.',

    'LUANY-CACHE-001' => 'Cache cleared (:count file(s) removed).',
    'LUANY-CACHE-002' => ':count compiled view(s) would be removed (dry run).',

    'LUANY-ROUTE-006' => 'Route cache cleared.',
    'LUANY-ROUTE-007' => 'No route cache to clear.',

    'LUANY-MIGRATE-001' => 'No migrations found.',

    'kind.controller.created' => 'Controller created',
    'kind.model.created'      => 'Model created',
    'kind.middleware.created' => 'Middleware created',
    'kind.provider.created'   => 'Provider created',
    'kind.request.created'    => 'Request created',
    'kind.seeder.created'     => 'Seeder created',
    'kind.test.created'       => 'Test created',
    'kind.view.created'       => 'View created',
    'kind.routes.created'     => 'Routes created',
    'kind.migration.created'  => 'Migration created',

    // Command descriptions come from each command's description().
    'LUANY-CLI-009' => 'Usage:',
    'LUANY-CLI-010' => 'Commands:',
    'LUANY-CLI-011' => 'command',
    'LUANY-CLI-012' => 'arguments',
];
