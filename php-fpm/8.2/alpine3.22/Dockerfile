ARG UID=33
ARG GID=33
ARG WITH_OPENSWOOLE=0

FROM docker.io/markkimsal/groundcontrol:latest AS groundcontrolimage
FROM dhi.io/php:8.2.30-alpine3.22-fpm AS prod-builder
FROM dhi.io/php:8.2.30-alpine3.22-dev AS builder
ARG UID
ARG GID
ARG WITH_OPENSWOOLE

LABEL org.opencontainers.image.base.name="docker.io/markkimsal/hardened-php-nginx:8.2" \
      org.opencontainers.image.description="PHP-FPM, Nginx, groundcontrol" \
      org.opencontainers.image.title="php-fpm-nginx"

COPY prebuildfs /

# Install required system packages and dependencies

RUN /usr/sbin/install_packages \
    libffi-dev \
    libzip-dev \
    libzip \
    bash \
    pcre2-dev \
    musl \
    musl-utils

SHELL ["/bin/bash", "-o", "errexit", "-o", "nounset", "-o", "pipefail", "-c"]

COPY appbuildfs /

RUN DOCKER_EXTENSIONS=( \
  "bcmath" \
  "ffi" \
  "pcntl" \
  "pdo_mysql" \
  "mysqli" \
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
); \
for component in "${pecl_extensions[@]}"; do \
  pecl install ${component}; \
done

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

RUN mkdir -p /run/nginx /run/php-fpm

# build-arg interpolation _requires_ a shell, no matter what docker build logs shows you
# (when RUN is used with exec style, JSON style params)
# so we will build passwd and group here for prod image to copy
RUN echo "nonroot:x:${GID}:nonroot\nshadow:x:42:\nnobody:x:65534:" >> /etc/prod-group
RUN echo "nonroot:x:${UID}:${GID}:nonroot:/home/nonroot:/bin/sh\nnobody:x:65534:65534:nobody:/nonexistent:/bin/sh" >> /etc/prod-passwd

# Nginx Builder
# ===========
# nginx-builder stage for nginx-deps.tar
FROM dhi.io/nginx:1-alpine3.22-dev AS nginx-builder

# Dev-Image
# ===========
# dev-image stage
FROM dhi.io/php:8.2.30-alpine3.22-dev AS dev-image
ARG UID
ARG GID
#make nonroot user the same on dev and prod
RUN echo "nonroot:x:${UID}:${GID}:nonroot:/home/nonroot:/bin/sh" >> /etc/passwd  && echo "nonroot:x:${UID}:${GID}:nonroot:/home/nonroot:/bin/sh" >> /etc/passwde

# Copy binaries, scripts, and config.
### Ground Control
COPY --from=builder /opt/php-8.2/lib /opt/php-8.2/lib
COPY --from=builder /opt/php-8.2/etc /opt/php-8.2/etc
COPY --from=builder /run /run
RUN ls -alh /run
COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx-builder /etc/nginx /etc/nginx
COPY --from=builder /lib/x86_64-linux-gnu/libffi.so.8 \
    /lib/x86_64-linux-gnu/libz.so.1 \
    /lib/x86_64-linux-gnu/libzip.so.5 \
    /lib/x86_64-linux-gnu/libffi.so.8 \
    /lib/x86_64-linux-gnu/libgcc_s.so.1 \
    /lib/x86_64-linux-gnu/libz.so.1 \
    /lib/x86_64-linux-gnu/libzip.so.5 \
    /lib/x86_64-linux-gnu/libbz2.so.1 \
    /lib/x86_64-linux-gnu/liblzma.so.5 \
    /lib/x86_64-linux-gnu/libzstd.so.1 \
    /lib/x86_64-linux-gnu/libcrypto.so.3 \
    /lib/x86_64-linux-gnu/libz.so.1 \
    /lib/x86_64-linux-gnu/
COPY --from=prod-builder /opt/php-8.2/sbin/php-fpm /opt/php-8.2/sbin/php-fpm
COPY --from=prod-builder /opt/php-8.2/etc/php-fpm.conf /opt/php-8.2/etc/php-fpm.conf
COPY --from=prod-builder /opt/php-8.2/etc/php-fpm.d /opt/php-8.2/etc/php-fpm.d
COPY --from=groundcontrolimage /groundcontrol /platform/
COPY --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx-builder /etc/nginx /etc/nginx


COPY prebuildfs /
COPY appbuildfs /
COPY approotfs /

#install tools for CI / local dev: git, jq, ps, ssh, etc
RUN /usr/sbin/install_packages \
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

WORKDIR "/app"
ENTRYPOINT ["/platform/groundcontrol", "/platform/webapp.toml"]

# Prod Image
# ===========
FROM dhi.io/php:8.2.30-alpine3.22-fpm AS prod-image
ARG UID
ARG GID

# Copy binaries, scripts, and config.
### Ground Control
COPY --from=builder /opt/php-8.2/lib /opt/php-8.2/lib
COPY --from=builder /opt/php-8.2/etc /opt/php-8.2/etc
COPY --from=builder /opt/php-8.2/bin/php /opt/php-8.2/bin/php


COPY --from=builder /usr/lib /usr/lib


#set nonroot userid to something more conventional like 33 for www-data
COPY --from=builder /etc/prod-passwd /etc/passwd
COPY --from=builder /etc/prod-group  /etc/group

COPY --from=groundcontrolimage /groundcontrol /platform/

COPY --chown=nonroot:nonroot --from=builder /run /run
COPY --chown=nonroot:nonroot --from=builder /run /var/run
COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx-builder /etc/nginx /etc/nginx
# nginx requires libcrypt.so.1, but another php-ext-dep also requires it


COPY approotfs /

WORKDIR "/app"
ENTRYPOINT ["/platform/groundcontrol", "/platform/webapp.toml"]
