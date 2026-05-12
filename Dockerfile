# --- Stage 1: build Tailwind CSS via standalone CLI ---
FROM debian:bookworm-slim AS tailwind-builder

ARG TAILWIND_VERSION=v3.4.17
ARG TARGETARCH=amd64

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl inotify-tools \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    case "${TARGETARCH}" in \
        amd64) bin="tailwindcss-linux-x64" ;; \
        arm64) bin="tailwindcss-linux-arm64" ;; \
        *) echo "Unsupported arch: ${TARGETARCH}" >&2; exit 1 ;; \
    esac; \
    curl -fsSL -o /usr/local/bin/tailwindcss \
        "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/${bin}"; \
    chmod +x /usr/local/bin/tailwindcss

WORKDIR /build
COPY . /build/

RUN tailwindcss \
        -c /build/tailwind.config.js \
        -i /build/themes/tailwind/input.css \
        -o /build/themes/tailwind/output.css \
        --minify

# --- Stage 2: PHP + Apache runtime ---
FROM php:8.2-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libzip-dev \
        unzip \
        default-mysql-client; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli \
        gd \
        mbstring \
        zip; \
    rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

RUN { \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=22M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
        echo 'date.timezone=Europe/Rome'; \
        echo 'session.cookie_httponly=1'; \
    } > /usr/local/etc/php/conf.d/gdrcd.ini

# Composer per autoloader PSR-4 + dipendenze runtime (Ratchet ecc.)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html/

COPY --from=tailwind-builder /build/themes/tailwind/output.css /var/www/html/themes/tailwind/output.css

# Installa dipendenze Composer (best-effort: se manca rete cade silenziosamente)
RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts || \
        echo "[gdrcd] composer install fallito al build; sara' ritentato all'entrypoint"; \
    fi

COPY docker/entrypoint.sh /usr/local/bin/gdrcd-entrypoint
RUN chmod +x /usr/local/bin/gdrcd-entrypoint

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["gdrcd-entrypoint"]
CMD ["apache2-foreground"]
