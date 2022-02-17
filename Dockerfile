FROM debian:bullseye

RUN set -e \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get update -y \
    && apt-get dist-upgrade -y \
    && apt-get install -y supervisor zip unzip git wget \
    && wget -q -O - https://packages.sury.org/php/README.txt | bash \
    && apt-get install -y git php8.0-cli composer php8.0-zip php8.0-mbstring php8.0-xml php8.0-curl php8.0-soap libsaxonb-java default-jre-headless \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ARG GIT_SOURCE=0
ARG GIT_REPO="https://github.com/dufreicom/api-json-cfdi-bridge.git"
ARG GIT_BRANCH="main"

COPY . /opt/sources

RUN set -e && \
    cp -r /opt/sources/ /opt/api-json-cfdi-bridge; 
    # if [ "$GIT_SOURCE" -eq 1 ]; then \
    #     git clone -b "${GIT_BRANCH}" "${GIT_REPO}" /opt/api-json-cfdi-bridge; \
    # else \
    #     cp -r /opt/sources/ /opt/api-json-cfdi-bridge; \
    # fi

WORKDIR /opt/api-json-cfdi-bridge

RUN set -e \
    && export COMPOSER_ALLOW_SUPERUSER=1 \
    && composer diagnose \
    && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && rm -rf "$(composer config cache-dir --global)" "$(composer config data-dir --global)" "$(composer config home --global)"

ARG XMLRESOLVER_PATH=""

RUN set -e && \
    if [ -n "$XMLRESOLVER_PATH" ]; then \
        bin/resource-sat-xml-download "$XMLRESOLVER_PATH"; \
    fi

EXPOSE 80

COPY ./docker/supervisord.conf /etc/supervisor/supervisord.conf

ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
