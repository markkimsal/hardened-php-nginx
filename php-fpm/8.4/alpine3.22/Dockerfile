FROM docker.io/markkimsal/groundcontrol:latest AS groundcontrolimage
FROM dhi.io/php:8.4.18-alpine3.22-fpm AS prod-builder
FROM dhi.io/php:8.4.18-alpine3.22-dev AS builder
ARG WITH_OPENSWOOLE=0

LABEL org.opencontainers.image.base.name="docker.io/markkimsal/hardened-php-nginx:8.4" \
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

# Nginx Builder
# ===========
# nginx-builder stage for nginx-deps.tar
FROM dhi.io/nginx:1-alpine3.22-dev AS nginx-builder
RUN ldd /usr/sbin/nginx 2>/dev/null| grep -v "libc\.so" | grep "=>" | awk '{print $3}' | xargs tar -chf nginx-deps.tar

# Dev-Image
# ===========
# dev-image stage
FROM dhi.io/php:8.4.18-alpine3.22-dev AS dev-image

#make nonroot user the same on dev and prod
#RUN sed -ie "s/_apt/nonroot/g" /etc/passwd && sed -ie "s/_apt/nonroot/g" /etc/passwde && sed -ie "s/_apt/nonroot/g" /etc/group && sed -ie "s/42/65532/" /etc/passwd && sed -ie "s/42/65532/" /etc/passwde
RUN echo "nonroot:x:65532:65532:nonroot:/home/nonroot:/bin/sh" >> /etc/passwd  && echo "nonroot:x:65532:65532:nonroot:/home/nonroot:/bin/sh" >> /etc/passwde

# Copy binaries, scripts, and config.
### Ground Control
COPY --from=builder /opt/php-8.4/lib /opt/php-8.4/lib
COPY --from=builder /opt/php-8.4/etc /opt/php-8.4/etc
COPY --from=builder /run /run
RUN ls -alh /run
COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /etc/nginx /etc/nginx
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
COPY --from=prod-builder /opt/php-8.4/sbin/php-fpm /opt/php-8.4/sbin/php-fpm
COPY --from=prod-builder /opt/php-8.4/etc/php-fpm.conf /opt/php-8.4/etc/php-fpm.conf
COPY --from=prod-builder /opt/php-8.4/etc/php-fpm.d /opt/php-8.4/etc/php-fpm.d
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
FROM dhi.io/php:8.4.18-alpine3.22-fpm AS prod-image
# Copy binaries, scripts, and config.
### Ground Control
COPY --from=builder /opt/php-8.4/lib /opt/php-8.4/lib
COPY --from=builder /opt/php-8.4/etc /opt/php-8.4/etc
COPY --from=builder /opt/php-8.4/bin/php /opt/php-8.4/bin/php


COPY --from=builder /usr/lib /usr/lib



COPY --from=groundcontrolimage /groundcontrol /platform/

COPY --chown=nonroot:nonroot --from=builder /run /run
COPY --chown=nonroot:nonroot --from=builder /run /var/run
COPY --chown=nonroot:nonroot --from=nginx-builder /var/log/nginx /var/log/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /var/cache/nginx /var/cache/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /usr/sbin/nginx /usr/sbin/nginx
COPY --chown=nonroot:nonroot --from=nginx-builder /etc/nginx /etc/nginx
#COPY --chown=nonroot:nonroot --from=nginx-builder /lib/x86_64-linux-gnu/libcrypt.so.1 /lib/x86_64-linux-gnu/libcrypt.so.1
COPY --chown=nonroot:nonroot --from=nginx-builder /nginx-deps.tar /nginx-deps.tar
#USER 0
#RUN ["/usr/bin/tar", "-xvf", "/nginx-deps.tar", "-C", "/", "--ignore-failed-read", "--warning=none", "--skip-old-files"]
#RUN ["tar", "-xvf", "/nginx-deps.tar", "-C", "/"]
#USER nonroot



#USER root
#COPY  --from=nginx-builder /nginx-deps.tar /nginx-deps.tar
#RUN ["/usr/bin/tar", "--ignore-failed-read", "--warning=none", "--skip-old-files", "--exclude='lib/x86_64-linux-gnu/libcrypt.so.1'", "-xvf", "/nginx-deps.tar", "-C", "/"]
#USER nonroot

COPY approotfs /

WORKDIR "/app"
ENTRYPOINT ["/platform/groundcontrol", "/platform/webapp.toml"]
