Hardened PHP + Nginx
===

Image that combines nginx with dhi.io hardened PHP.  Uses groundcontrol to manage various entrypoints.

Items this project adds to the dhi.io hardened fpm image:
  * nginx
  * cron
  * php (cli sapi)
  * tar
  * ldd
  * various php extensions


Items this project adds to the dhi.io hardened -dev image:
  * all items from the -fpm image, plus
  * curl
  * composer
  * jq
  * git
  * unzip
  * gpg
  * procps
  * openssh-client


This project's `*-dev` image is meant to be used as a local development image AND as a CI/CD pipeline image.

The hardened production image `*-fpm` contains no shell,so you will be unable to run artisan commands on production without some extra steps.

Run php-fpm & Nginx
---

`/platform/webapp.toml`
```toml
[[processes]]
name = "php"
run = [ "/opt/php/sbin/php-fpm" ]
stop="SIGQUIT"

[[processes]]
name = "nginx"
run = [ "/usr/sbin/nginx", "-g", "daemon off; user nonroot;" ]

```

Usage with Laravel
===
This image is designed to run as a front-end server with Nginx + PHP-fpm, an artisan scheduler run via `cron`, and/or as a worker queue.


Run a worker queue. 
---
```Dockerfile
FROM hardened-php-nginx

ADD worker-queue.toml  /platform/worker-queue.toml
ENTRYPOINT ["/platform/groundcontrol", "/platform/worker-queue.toml"]
```

`worker-queue.toml`

```toml
[[processes]]
name = "artisan-queue"
run = ["/opt/php/sbin/php", "artisan", "queue:work"]
stop="SIGQUIT"
```

Run artisan scheduler
---

```Dockerfile
FROM hardened-php-nginx

ADD worker-queue.toml  /platform/artisan-scheduler.toml
ENTRYPOINT ["/platform/groundcontrol", "/platform/artisan-scheduler.toml"]
```

`artisan-scheduler.toml`

```toml
[[processes]]
name = "setup-cron"
pre = ["php",  "-r", "file_put_contents('/etc/cron.d/laravel-artisan-schedule-run', '* * * * * nonroot {{CRON_COMMAND}} >> /dev/null 2>&1');\n" ]

[[processes]]
name = "cron"
run = ["/usr/bin/cron", "-f"]
```

Docker cli command
```
docker run -e "CRON_COMMAND=/opt/php/bin/php artisan schedule:run" ...
```

Docker compose file:
```yaml
services:
    scheduler:
        image: your-app:latest
        environment:
          CRON_COMMAND: /opt/php/bin/php artisan schedule:run
        entrypoint:
          - /platform/groundcontrol
          - /platform/artisan-schedule.toml

```

Optimize your configuration
---
You cannot optimize your config files in your CI/CD pipeline without exposing all secrets to the pipeline environment.  If your secrets are only available in the live environment, you must `php artisan config:cache` in the live environment.

`/platform/laravel.toml`
```toml
[[processes]]
name = "optimize-laravel"
pre = "php artisan optimize"

[[processes]]
name = "migrate database"
pre = "php artisan migrate -f"

[[processes]]
name = "php"
run = [ "/opt/php-8.2/sbin/php-fpm" ]
stop="SIGQUIT"

[[processes]]
name = "nginx"
run = {only-env = [], command=["/usr/sbin/nginx", "-g", "daemon off; user nonroot;"]}
```
