FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN set -eux; \
    if [ -f composer.json ]; then APP_DIR="/var/www/html"; elif [ -f backend/composer.json ]; then APP_DIR="/var/www/html/backend"; else echo "composer.json not found"; exit 1; fi; \
    mkdir -p "$APP_DIR/storage/framework/sessions" "$APP_DIR/storage/framework/views" "$APP_DIR/storage/framework/cache" "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache"; \
    if [ -f "$APP_DIR/docker/env.docker" ]; then cp "$APP_DIR/docker/env.docker" "$APP_DIR/.env"; elif [ -f "$APP_DIR/.env.example" ]; then cp "$APP_DIR/.env.example" "$APP_DIR/.env"; else touch "$APP_DIR/.env"; fi; \
    if [ -f "$APP_DIR/.env" ]; then tr -d '\r' < "$APP_DIR/.env" | grep -vE '^[[:space:]]*DB_' > "$APP_DIR/.env.tmp" && mv "$APP_DIR/.env.tmp" "$APP_DIR/.env"; fi; \
    APP_KEY="base64:$(php -r "echo base64_encode(random_bytes(32));")" composer --working-dir="$APP_DIR" install --no-interaction --optimize-autoloader; \
    KEY="base64:$(php -r "echo base64_encode(random_bytes(32));")" && grep -vE '^[[:space:]]*APP_KEY=' "$APP_DIR/.env" > "$APP_DIR/.env.tmp" && mv "$APP_DIR/.env.tmp" "$APP_DIR/.env" && printf 'APP_KEY=%s\n' "$KEY" >> "$APP_DIR/.env"; \
    cp "$APP_DIR/docker/entrypoint.sh" /usr/local/bin/app-entrypoint.sh; \
    chmod +x /usr/local/bin/app-entrypoint.sh; \
    chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8000

ENTRYPOINT ["sh", "/usr/local/bin/app-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
