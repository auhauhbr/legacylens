<?php

return [
    'ignored_directories' => [
        'vendor',
        'node_modules',
        'storage/logs',
        'bootstrap/cache',
        '.git',
        '.idea',
        '.vscode',
        'public/build',
        'coverage',
    ],

    'ignored_files' => [
        '.env',
        '.env.*',
        '*.key',
        '*.pem',
        '*.log',
        '*.sqlite',
        '*.db',
    ],

    'allowed_commands' => [
        'composer_audit',
        'composer_outdated',
        'artisan_route_list',
        'git_current_branch',
    ],

    'command_definitions' => [
        'composer_audit' => ['composer', 'audit', '--format=json'],
        'composer_outdated' => ['composer', 'outdated', '--format=json'],
        'artisan_route_list' => ['php', 'artisan', 'route:list', '--json'],
        'git_current_branch' => ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
    ],

    'process_timeout_seconds' => 30,
    'max_process_output_bytes' => 1_048_576,
    'max_file_size_mb' => 2,

    'sensitive_directories' => [
        '/',
        '/etc',
        '/home',
        '/root',
        '/var',
        '/usr',
    ],
];
