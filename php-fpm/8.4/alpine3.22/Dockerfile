ARG UID=33
ARG GID=33
ARG WITH_OPENSWOOLE=0
ARG WITH_OTEL=0

FROM docker.io/markkimsal/groundcontrol:latest AS groundcontrolimage
FROM dhi.io/php:8.4.24-alpine3.22-fpm AS prod-builder
FROM dhi.io/php:8.4.24-alpine3.22-dev AS builder
ARG UID
ARG GID
ARG WITH_OPENSWOOLE
ARG WITH_OTEL

LABEL org.opencontainers.image.base.name="docker.io/markkimsal/hardened-php-nginx:8.4" \
      org.opencontainers.image.description="PHP-FPM, Nginx, groundcontrol" \
      org.opencontainers.image.title="php-fpm-nginx"

COPY prebuildfs /

# Install required system packages and dependencies

RUN --mount=type=cache,target=/var/cache/apk \
    /usr/sbin/install_packages \
    busybox \
    libffi-dev \
    libzip-dev \
    libzip \
    libpng-dev \
    jpeg \
    jpeg-dev \
    freetype \
    freetype-dev \
    libwebp-dev \
    zlib \
    zlib-dev \
    bash \
    pcre2-dev \
    musl \
    musl-utils

SHELL ["/bin/bash", "-o", "errexit", "-o", "nounset", "-o", "pipefail", "-c"]

COPY appbuildfs /

RUN DOCKER_EXTENSIONS=( \
  "gd --with-freetype --with-jpeg --with-webp" \
  "bcmath" \
  "ffi" \
  "mysqli" \
  "pcntl" \
  "pdo_mysql" \
  "sysvmsg" \
  "sysvsem" \
  "sysvshm" \
  "zip" \
) ; \
cd /platform/; \
for COMPONENT in "${DOCKER_EXTENSIONS[@]}"; do \
    echo "--------------------------- installing extension: ${COMPONENT}" ; \
    bash ./docker-php-ext-install ${COMPONENT} ; \
done

RUN DOCKER_DEFAULT_EXTENSIONS=( \
  "bcmath" \
  "pcntl" \
  "pdo_mysql" \
  "mysqli" \
  "zip" \
) ; \
cd /platform/; \
for COMPONENT in "${DOCKER_DEFAULT_EXTENSIONS[@]}"; do \
    echo "--------------------------- enabling extension: ${COMPONENT}" ; \
    bash ./docker-php-ext-enable ${COMPONENT} ; \
done

RUN pecl_extensions=( \
  "xdebug" \
  "redis" \
); \
for component in "${pecl_extensions[@]}"; do \
  pecl install ${component}; \
done

# GRPC
RUN set -ex \
    pecl install grpc-1.81.0;

# PROTOBUF
RUN set -ex \
    pecl install protobuf-5.35.1;

# OPENTELEMETRY
RUN set -ex \
    &&  if [ "$WITH_OTEL" -ne "0" ] ; then \
        pecl install opentelemetry; \
    fi

# SWOOLE
RUN set -ex \
    &&  if [ "$WITH_OPENSWOOLE" -ne "0" ] ; then \
        pecl install openswoole; \
    	  bash /platform/docker-php-ext-enable openswoole ; \
    fi

SHELL ["/bin/bash",  "-o", "nounset", "-c"]
# alpine musl ldd weirdly dies on undefined symbols
RUN \
    PHP_EXT_DIR="$(php -d 'display_errors=stderr' -r 'echo ini_get("extension_dir");')"; \
    for so in ${PHP_EXT_DIR}/*.so; do \
        #ldd $so 2>/dev/null ; \
        ldd $so 2>/dev/null | grep -v "libc\." | grep -v "ld\-musl" | grep "=>" | awk '{print $3}' >> /php-ext-deps.txt; \
    done; \
    echo "found extension dependencies: "; \
    cat  /php-ext-deps.txt;


# build-arg interpolation _requires_ a shell, no matter what docker build logs shows you
# (when RUN is used with exec style, JSON style params)
# so we will build passwd and group here for prod image to copy
RUN echo "nonroot:x:${GID}:nonroot\nshadow:x:42:\nnobody:x:65534:" >> /etc/prod-group
RUN echo "nonroot:x:${UID}:${GID}:nonroot:/home/nonroot:/bin/sh\nnobody:x:65534:65534:nobody:/nonexistent:/bin/sh" >> /etc/prod-passwd

# cron / supercronic

# Latest releases available at https://github.com/aptible/supercronic/releases
ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.44/supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=6eb0a8e1e6673675dc67668c1a9b6409f79c37bc \
    SUPERCRONIC=supercronic-linux-amd64

RUN curl -fsSLO "$SUPERCRONIC_URL" \
 && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
 && chmod +x "$SUPERCRONIC" \
 && mv "$SUPERCRONIC" "/usr/bin/supercronic"

# Nginx Builder
# ===========
# nginx-builder stage for nginx-deps.tar
FROM dhi.io/nginx:1-alpine3.22-dev AS nginx-builder

# Dev Image
# ===========
# dev-image stage
FROM dhi.io/php:8.4.24-alpine3.22-dev AS dev-image
ARG UID
ARG GID
#make nonroot user the same on dev and prod
RUN sed -ie "s/65532/${GID}/" /etc/passwd && sed -ie "s/65532/${GID}/" /etc/group
RUN mkdir -p /home/nonroot && chown ${UID}:${GID} /home/nonroot

# Copy binaries, scripts, and config.
COPY --from=builder /opt/php-8.4/lib /opt/php-8.4/lib
COPY --from=builder /opt/php-8.4/etc /opt/php-8.4/etc
COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx-builder /etc/nginx /etc/nginx
COPY --from=builder /usr/lib/libffi.so.8 \
    /usr/lib/libgcc_s.so.1 \
    /usr/lib/libzip.so.5 \
    /usr/lib/libfreetype.so.6 \
    /usr/lib/libjpeg.so.8 \
    /usr/lib/libpng16.so.16 \
    /usr/lib/libsharpyuv.so.0 \
    /usr/lib/libwebp.so.7 \
    /usr/lib/libcrypto.so.3 \
    /usr/lib/libz.so.1 \
    /usr/lib/
COPY --from=prod-builder /opt/php-8.4/sbin/php-fpm /opt/php-8.4/sbin/php-fpm
COPY --from=prod-builder /opt/php-8.4/etc/php-fpm.conf /opt/php-8.4/etc/php-fpm.conf
COPY --from=prod-builder /opt/php-8.4/etc/php-fpm.d /opt/php-8.4/etc/php-fpm.d

### Ground Control
COPY --from=groundcontrolimage /groundcontrol /platform/

# cron / supercronic
COPY --chown=nonroot:nonroot --from=builder /usr/bin/supercronic /usr/bin/supercronic
# COPY --chown=nonroot:nonroot --from=builder /etc/cron.d /etc/cron.d
# COPY --chown=nonroot:nonroot --from=builder /var/spool/cron /var/spool/cron
# COPY --chown=nonroot:nonroot --from=builder /usr/sbin/cron /usr/sbin/cron

COPY prebuildfs /
COPY appbuildfs /
COPY --chown=nonroot:nonroot approotfs /

#install tools for CI / local dev: git, jq, ps, ssh, etc
RUN --mount=type=cache,target=/var/cache/apt \
  --mount=type=cache,target=/var/lib/apt/lists \
  --mount=type=cache,target=/var/cache/apk \
  /usr/sbin/install_packages \
  dash \
  git \
  jq \
  openssh-client \
  procps \
  tzdata \
  unzip \
  gpg \
  curl

#install composer
RUN /platform/docker-composer-install

USER nonroot
WORKDIR "/app"
ENTRYPOINT ["/platform/groundcontrol", "/platform/webapp.toml"]

# Prod Image
# ===========
FROM dhi.io/php:8.4.24-alpine3.22-fpm AS prod-image
ARG UID
ARG GID

# Copy binaries, scripts, and config.
### Ground Control
COPY --from=builder /opt/php-8.4/lib /opt/php-8.4/lib
COPY --from=builder /opt/php-8.4/etc /opt/php-8.4/etc
COPY --from=builder /opt/php-8.4/bin/php /opt/php-8.4/bin/php


COPY --from=builder /usr/lib /usr/lib


#set nonroot userid to something more conventional like 33 for www-data
# RUN sed -ie "s/65532/${GID}/" /etc/passwd && sed -ie "s/65532/${GID}/" /etc/group
COPY --from=builder /etc/prod-passwd /etc/passwd
COPY --from=builder /etc/prod-group  /etc/group

COPY --from=groundcontrolimage /groundcontrol /platform/

COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx-builder /etc/nginx /etc/nginx
# nginx requires libcrypt.so.1, but another php-ext-dep also requires it

# cron / supercronic
COPY --chown=nonroot:nonroot --from=builder /usr/bin/supercronic /usr/bin/supercronic
# COPY --chown=nonroot:nonroot --from=builder /etc/cron.d /etc/cron.d
# COPY --chown=nonroot:nonroot --from=builder /var/spool/cron /var/spool/cron
# COPY --chown=nonroot:nonroot --from=builder /usr/sbin/cron /usr/sbin/cron

COPY --chown=nonroot:nonroot --from=builder /bin/busybox /bin/sh


COPY --chown=nonroot:nonroot approotfs /

USER nonroot
WORKDIR "/app"
ENTRYPOINT ["/platform/groundcontrol", "/platform/webapp.toml"]
