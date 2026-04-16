<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Railway injecta DATABASE_URL no processo; getenv() evita casos em que env()/cache apontam para 127.0.0.1.
        $raw = self::runtimeEnv('DATABASE_URL') ?? self::runtimeEnv('DB_URL');
        if ($raw !== null) {
            $parts = parse_url($raw) ?: [];
            $host = $parts['host'] ?? '';
            if ($host === '') {
                return;
            }
            $path = $parts['path'] ?? '';
            $database = ltrim($path, '/');

            config([
                // Evita depender do parser de URL do driver em runtime.
                'database.connections.pgsql.url' => null,
                'database.connections.pgsql.host' => $host,
                'database.connections.pgsql.port' => isset($parts['port']) ? (string) $parts['port'] : null,
                'database.connections.pgsql.database' => $database !== '' ? $database : null,
                'database.connections.pgsql.username' => isset($parts['user']) ? urldecode($parts['user']) : null,
                'database.connections.pgsql.password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
            ]);
        }
    }

    public function boot(): void
    {
        $this->syncPgsqlFromProcessEnv();

        Gate::policy(Event::class, EventPolicy::class);

        Gate::define('access-organizer-api', fn (?User $user): bool => $user?->isOrganizer() ?? false);
        Gate::define('access-participant-api', fn (?User $user): bool => $user?->isParticipant() ?? false);
    }

    /**
     * Railway: variáveis DB_* vêm no processo. Config em cache / url residual pode fazer o PDO
     * usar 127.0.0.1 — re-aplicar sempre a partir de getenv e purgar a conexão.
     */
    private function syncPgsqlFromProcessEnv(): void
    {
        $host = self::runtimeEnv('DB_HOST');
        if ($host === null) {
            return;
        }

        $port = self::runtimeEnv('DB_PORT');
        $database = self::runtimeEnv('DB_DATABASE');
        $username = self::runtimeEnv('DB_USERNAME');
        $password = self::runtimeEnv('DB_PASSWORD');

        config([
            'database.connections.pgsql.url' => null,
            'database.connections.pgsql.host' => $host,
            'database.connections.pgsql.port' => is_string($port) && $port !== '' ? $port : '5432',
            'database.connections.pgsql.database' => is_string($database) && $database !== '' ? $database : null,
            'database.connections.pgsql.username' => is_string($username) && $username !== '' ? $username : null,
            'database.connections.pgsql.password' => is_string($password) ? $password : null,
        ]);

        try {
            DB::purge('pgsql');
        } catch (\Throwable) {
            // ainda não há manager de DB
        }
    }

    private static function runtimeEnv(string $key): ?string
    {
        $candidates = [
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
        ];
        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        $g = getenv($key);
        if (is_string($g) && $g !== '') {
            return $g;
        }

        return null;
    }
}
