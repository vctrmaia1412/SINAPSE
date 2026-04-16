<?php

$inDocker = file_exists('/.dockerenv');
$isProduction = env('APP_ENV') === 'production';
$isRender = (bool) env('RENDER', false) || env('RENDER_SERVICE_ID') !== null;
$shouldForcePgsql = $inDocker || $isProduction || $isRender;

$configured = env('DB_CONNECTION');
$defaultConnection = ($shouldForcePgsql && ($configured === null || $configured === '' || $configured === 'sqlite'))
    ? 'pgsql'
    : env('DB_CONNECTION', 'pgsql');

// Só usar host/port/DB_* quando NÃO há URL — senão o Laravel pode preferir 127.0.0.1 em vez de parsear DATABASE_URL.
$dbUrl = env('DATABASE_URL');
if ($dbUrl === null || $dbUrl === '') {
    $dbUrl = env('DB_URL');
}
$dbUrl = ($dbUrl !== null && $dbUrl !== '') ? $dbUrl : null;

$pgsql = [
    'driver' => 'pgsql',
    'url' => $dbUrl,
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),
];

if ($dbUrl === null) {
    $pgsql['host'] = env('DB_HOST', '127.0.0.1');
    $pgsql['port'] = env('DB_PORT', '5432');
    $pgsql['database'] = env('DB_DATABASE', 'devevents');
    $pgsql['username'] = env('DB_USERNAME', 'devevents');
    $pgsql['password'] = env('DB_PASSWORD', '');
} else {
    $parts = parse_url($dbUrl) ?: [];
    $path = $parts['path'] ?? '';
    $dbname = ltrim($path, '/');

    // Railway fornece uma URL única; preencher os campos evita fallback para 127.0.0.1.
    $pgsql['host'] = $parts['host'] ?? null;
    $pgsql['port'] = isset($parts['port']) ? (string) $parts['port'] : null;
    $pgsql['database'] = $dbname !== '' ? $dbname : null;
    $pgsql['username'] = isset($parts['user']) ? urldecode($parts['user']) : null;
    $pgsql['password'] = isset($parts['pass']) ? urldecode($parts['pass']) : null;
}

return [
    'default' => $defaultConnection,
    'connections' => [
        'pgsql' => $pgsql,
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
