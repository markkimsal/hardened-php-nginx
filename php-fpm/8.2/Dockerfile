FROM docker.io/markkimsal/groundcontrol:latest AS groundcontrolimage
FROM dhi.io/nginx:1-debian13 as nginx
FROM dhi.io/php:8.2.30-debian13-dev AS builder

LABEL org.opencontainers.image.base.name="docker.io/markkimsal/hardened-php-nginx:8.2" \
      org.opencontainers.image.description="PHP-FPM, Nginx, groundcontrol" \
      org.opencontainers.image.title="php-fpm-nginx"

COPY prebuildfs /


# Install required system packages and dependencies
RUN install_packages ca-certificates curl git krb5-user libbz2-1.0 libcom-err2 libcrypt1 libffi8 libgcc-s1 libgmp10 libgnutls30 libgss-dev libgssapi-krb5-2 libhogweed6 libidn2-0 libk5crypto3 libkeyutils1 libkrb5-3 libkrb5-dev libkrb5support0 liblzma5 libmariadb3 libncursesw6 libnettle8 libnsl2 libp11-kit0 libreadline8 libsasl2-2 libsasl2-modules libsqlite3-0 libssl3 libstdc++6 libtasn1-6 libtinfo6 libtirpc3 libudev1 locales netbase openssh-client procps tzdata zlib1g locales libpcre2-dev libpcre2-posix3

RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen
RUN locale-gen en_US.UTF-8
RUN update-locale en_US.UTF-8
RUN update-locale C.utf8
RUN cat /etc/locale.gen
RUN export LC_ALL=C.utf8

RUN install_packages zlib1g-dev libicu-dev

SHELL ["/bin/bash", "-o", "errexit", "-o", "nounset", "-o", "pipefail", "-c"]

COPY appbuildfs /

#  "opcache" \
RUN DOCKER_EXTENSIONS=( \
  "bcmath" \
  "intl" \
  "pcntl" \
  "pdo" \
  "pdo_mysql" \
  "mysqli" \
  "sysvmsg" \
  "sysvsem" \
  "sysvshm" \
) ; \
cd /platform/; \
for COMPONENT in "${DOCKER_EXTENSIONS[@]}"; do \
    echo "--------------------------- installing extension: ${COMPONENT}" ; \
    bash ./docker-php-ext-install ${COMPONENT} ; \
done
  
RUN PECL_EXTENSIONS=( \
  "xdebug" \
); \
for COMPONENT in "${PECL_EXTENSIONS[@]}"; do \
  pecl install ${COMPONENT}; \
done


# Copy binaries, scripts, and config.
RUN mkdir -p /app
WORKDIR /app

### Ground Control
COPY --from=groundcontrolimage /groundcontrol ./
RUN mkdir -p /var/log/nginx /var/cache/nginx /etc/nginx /run/nginx
COPY --from=nginx /usr/sbin/nginx /usr/sbin/nginx
COPY --from=nginx /etc/nginx /etc/nginx

ENTRYPOINT ["/app/groundcontrol", "/platform/groundcontrol.toml"]
