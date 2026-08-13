# ---------------------------------------------------------------------------
# MenuTag Generator — app/worker image (WS-6, spec §6)
#
# Sail's PHP image does NOT contain the Python geometry engine toolchain and
# is not published on any registry (Sail builds it locally from
# vendor/laravel/sail/runtimes/<php>/Dockerfile). This Dockerfile therefore
# EXTENDS the Sail runtime by reproducing it verbatim (base section below is
# a faithful copy of vendor/laravel/sail/runtimes/8.5/Dockerfile) and adding
# the MenuTag engine layer at the end: python3, python3-venv, the system
# libraries needed by the pinned wheels (see engine/requirements.txt and
# engine/README.md) and the virtualenv, created AT BUILD TIME.
#
# The virtualenv deliberately lives in /opt/menutag-engine/venv, OUTSIDE
# /var/www/html: docker-compose bind-mounts the project over /var/www/html,
# which would shadow anything baked there — and a venv created on the host
# (macOS/Windows) would carry wheels for the wrong platform anyway. The
# MENUTAG_ENGINE_PYTHON environment variable (set here and echoed in
# docker-compose.yml) points config/product.php at the image venv; real
# environment variables win over .env values, so no .env edit is needed.
#
# Build context: the repository root (composer install must have run first,
# exactly like Sail itself requires — the COPY lines below read the runtime
# support files from vendor/laravel/sail).
# ---------------------------------------------------------------------------

FROM ubuntu:24.04

LABEL maintainer="MenuTag Generator"

ARG WWWGROUP=1000
ARG NODE_VERSION=24
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=18
ARG PHP_EXTENSIONS=""

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.5-cli \
        php8.5-dev \
        php8.5-pgsql \
        php8.5-sqlite3 \
        php8.5-gd \
        php8.5-curl \
        php8.5-mongodb \
        php8.5-imap \
        php8.5-mysql \
        php8.5-mbstring \
        php8.5-xml \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-soap \
        php8.5-intl \
        php8.5-readline \
        php8.5-ldap \
        php8.5-msgpack \
        php8.5-igbinary \
        php8.5-redis \
        php8.5-swoole \
        php8.5-memcached \
        php8.5-pcov \
        php8.5-imagick \
        php8.5-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && COMPOSER_HOME=/usr/local/share/composer composer global require cpx/cpx:^2.0 \
    && ln -s /usr/local/share/composer/vendor/bin/cpx /usr/local/bin/cpx \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm pnpm bun corepack \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    && npx -y playwright install-deps \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get install -y postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN if [ -n "$PHP_EXTENSIONS" ]; then \
    apt-get update \
    && apt-get install -y $(for ext in $PHP_EXTENSIONS; do echo "php8.5-$ext"; done) \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*; \
fi

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.5

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN git config --global --add safe.directory /var/www/html

COPY vendor/laravel/sail/runtimes/8.5/start-container /usr/local/bin/start-container
COPY vendor/laravel/sail/runtimes/8.5/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY vendor/laravel/sail/runtimes/8.5/php.ini /etc/php/8.5/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

# Deviation from the faithful Sail copy above: more start retries, so
# worker/scheduler survive the window between container start and
# `migrate:fresh` on a brand new database instead of giving up for good
# (see docker/supervisord.conf for why).
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ---------------------------------------------------------------------------
# MenuTag geometry engine layer (spec §6 WS-6)
#
# - python3 (Ubuntu 24.04 → 3.12, matching the pinned wheels) is already in
#   the Sail base; python3-venv adds ensurepip, required to create the venv.
# - libglib2.0-0t64 is the only extra shared library the pinned wheels need
#   at runtime (opencv-python-headless links libglib/gthread; shapely,
#   trimesh, manifold3d, segno, svgelements and numpy ship self-contained
#   manylinux wheels — see engine/README.md for the library rationale).
# - The venv is created at BUILD time in /opt/menutag-engine/venv (outside
#   the bind-mounted /var/www/html, see header) from the pinned
#   engine/requirements.txt, so app and worker containers boot ready to run
#   the engine without touching the network.
# ---------------------------------------------------------------------------

RUN apt-get update \
    && apt-get install -y python3-venv libglib2.0-0t64 \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY engine/requirements.txt /opt/menutag-engine/requirements.txt

RUN python3 -m venv /opt/menutag-engine/venv \
    && /opt/menutag-engine/venv/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/menutag-engine/venv/bin/pip install --no-cache-dir -r /opt/menutag-engine/requirements.txt

# Real environment beats .env (phpdotenv is immutable): config/product.php
# resolves the engine interpreter from here inside every container.
ENV MENUTAG_ENGINE_PYTHON=/opt/menutag-engine/venv/bin/python3

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
