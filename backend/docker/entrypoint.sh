#!/bin/sh
set -e

if [ -z "${APP_KEY}" ]; then
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

APP_DIR="/var/www/html"
if [ ! -f "$APP_DIR/artisan" ] && [ -f "/var/www/html/backend/artisan" ]; then
  APP_DIR="/var/www/html/backend"
fi

cd "$APP_DIR"

if [ -f .env ]; then
  tr -d '\r' < .env | grep -vE '^[[:space:]]*DB_' > .env.tmp && mv .env.tmp .env
  # Com DATABASE_URL injectada (Railway), linhas no .env podem confundir o Dotenv — usar só o ambiente.
  if [ -n "${DATABASE_URL:-}" ] || [ -n "${DB_URL:-}" ]; then
    grep -vE '^[[:space:]]*(DATABASE_URL|DB_URL)=' .env > .env.tmp && mv .env.tmp .env
  fi
fi

# Garantir APP_KEY no .env antes de qualquer artisan (evita falhas se só o ficheiro for lido).
if [ -n "${APP_KEY:-}" ] && [ -f .env ]; then
  grep -vE '^[[:space:]]*APP_KEY=' .env > .env.tmp && mv .env.tmp .env
  printf 'APP_KEY=%s\n' "$APP_KEY" >> .env
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
# Se o Railway injectar DATABASE_URL/DB_URL (Postgres ligado) MAS o painel já tiver DB_*,
# NÃO fazer unset — senão o Laravel perde host e cai em 127.0.0.1.
if [ -n "${DATABASE_URL:-}" ] || [ -n "${DB_URL:-}" ]; then
  if [ -z "${DB_HOST:-}" ]; then
    unset DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
  fi
else
  export DB_HOST="${DB_HOST:-postgres}"
  export DB_PORT="${DB_PORT:-5432}"
  export DB_DATABASE="${DB_DATABASE:-devevents}"
  export DB_USERNAME="${DB_USERNAME:-devevents}"
  export DB_PASSWORD="${DB_PASSWORD:-devevents_secret}"
fi

# Railway: com DB_HOST no painel, forçar credenciais discretas no .env e tirar URL do processo.
# Assim o Dotenv e config/database.php não ficam sem host e não caem no default 127.0.0.1.
if [ -n "${DB_HOST:-}" ]; then
  unset DATABASE_URL DB_URL
  if [ -f .env ]; then
    tr -d '\r' < .env | grep -vE '^[[:space:]]*(DB_|DATABASE_URL|DB_URL)=' > .env.tmp && mv .env.tmp .env
  fi
  {
    printf 'DB_CONNECTION=%s\n' "${DB_CONNECTION:-pgsql}"
    printf 'DB_HOST=%s\n' "${DB_HOST}"
    printf 'DB_PORT=%s\n' "${DB_PORT:-5432}"
    printf 'DB_DATABASE=%s\n' "${DB_DATABASE:-}"
    printf 'DB_USERNAME=%s\n' "${DB_USERNAME:-}"
    printf 'DB_PASSWORD=%s\n' "${DB_PASSWORD:-}"
  } >> .env
fi

rm -f bootstrap/cache/config.php 2>/dev/null || true

php artisan optimize:clear || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

if [ "${RUN_MIGRATIONS_ON_BOOT:-false}" = "true" ]; then
  php artisan migrate --force || exit 1
fi

# Sem Railway CLI: RUN_DB_SEED_ON_BOOT=true no painel → um deploy → voltar a false.
# Segunda execução falha em duplicados; || true evita que o contentor morra com set -e.
if [ "${RUN_DB_SEED_ON_BOOT:-false}" = "true" ]; then
  php artisan db:seed --force || true
fi

# Railway define PORT (ex.: 8080); o CMD da imagem usa 8000.
if [ "$#" -ge 3 ] && [ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "serve" ]; then
  PORT_NUM=$((${PORT:-8000}+0))
  exec php artisan serve --host=0.0.0.0 --port="$PORT_NUM"
fi

exec "$@"
