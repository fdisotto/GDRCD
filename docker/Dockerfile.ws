# GDRCD WebSocket chat server (Ratchet) — container dedicato.
#
# Eseguito da docker-compose.override.yml come servizio `gdrcd-ws`, espone
# ws://:8082 e fa polling del DB ogni ~1s per pushare i messaggi chat ai
# client connessi. Vedi src/WebSocket/ChatHandler.php.
#
# Build:   docker compose build gdrcd-ws
# Run:     docker compose up -d gdrcd-ws
FROM php:8.2-cli

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        zip; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Il source viene bind-mountato dal compose (vedi docker-compose.override.yml)
# quindi non copiamo dentro l'immagine: il container si avvia leggendo i
# file dal volume host, identico al servizio `web` in dev.

EXPOSE 8082

CMD ["php", "bin/gdrcd-ws-server.php"]
