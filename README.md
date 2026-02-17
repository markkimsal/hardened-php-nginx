Hardened PHP + Nginx
===
Image that combines nginx with dhi.io hardened PHP.  Uses groundcontrol to manage various entrypoints.

One image for your local, CI, and prod environments.  One image for your FPM, scheduler, worker instances.

Items this project adds to the dhi.io hardened `*-fpm` image:
  * nginx
  * cron
  * php (cli sapi)
  * various php extensions


Items this project adds to the dhi.io hardened `*-dev` image:
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


|                      | hardened/fpm (prod) | dhi.io/fpm (prod)  | hardened/dev (ci/local) | dhi.io/dev      |
|----------------------|---------------------|--------------------|-------------------------|-----------------|
| php-fpm              | X                   |  X                 | X                       | X               |
| php (cli)            | X                   |                    | X                       | X               |
| nginx                | X                   |                    | X                       |                 |
| cron (\*)            | X                   |                    | X                       |                 |
| composer             |                     |                    | X                       |                 |
| git                  |                     |                    | X                       |                 |
| jq                   |                     |                    | X                       |                 |
| curl                 |                     |                    | X                       |                 |
| unzip                |                     |                    | X                       |                 |
| gpg                  |                     |                    | X                       |                 |
| procps               |                     |                    | X                       |                 |
| ssh                  |                     |                    | X                       |                 |


Extensions built
|                      | hardened/fpm (prod) | dhi.io/fpm (prod)  | hardened/dev (ci/local) | dhi.io/dev      |
|----------------------|---------------------|--------------------|-------------------------|-----------------|
| BCMATH               | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| Intl                 | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| cur                  | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| dom                  | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| libxml               | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| mbstring             | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| mysqlnd              | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| opcache              | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| openssl              | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| sqlite3 (PDO)        | 🚀                   |  🚀                 | 🚀                       | 🚀               |
| ZIP                  | 🚀                   |                     | 🚀                       |                  |
| mysql (PDO)          | 🚀                   |                     | 🚀                       |                  |
| pcntl                | 🚀                   |                     | 🚀                       |                  |
| OpenSwooole          | ✅                   |                     | ✅                       |                  |
| FFI                  | ✅                   |                     | ✅                       |                  |
| Xdebug               | ✅                   |                     | ✅                       |                  |
| sysvshm              | ✅                   |                     | ✅                       |                  |


🚀 - built and enabled by default
✅ - built, but not enabled

\* Cron is available on all alpine image variants because it is part of busybox

Run a basic PHP-fpm & Nginx setup
===

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
`SIGQUIT` is designed to give some amount of grace period to running jobs before moving on to hard shutdown.

```Dockerfile
FROM hardened-php-nginx

ADD worker-queue.toml  /platform/worker-queue.toml
ENTRYPOINT ["/platform/groundcontrol", "/platform/worker-queue.toml"]
```

`worker-queue.toml`

```toml
[[processes]]
name = "artisan-queue"
run = ["/opt/php/sbin/php", "artisan", "queue:work", "{{QUEUE_WORK_ARGS}}"]
stop="SIGQUIT"
```

Docker cli command
```
docker run -e "QUEUE_WORK_ARGS=redis --tries=3 --backoff=3" ...
```

Docker compose file:
```yaml
services:
    scheduler:
        image: your-app:latest
        environment:
          QUEUE_WORK_ARGS: redis --tries=3 --backoff=3
        entrypoint:
          - /platform/groundcontrol
          - /platform/artisan-schedule.toml

```



Run artisan scheduler
---
Run the artisan scheduler from a cron job.

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

Deploy production Laravel app
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


How to build
===
You can use this projects's `*-dev` image as your starting FROM line to make changes to your own image, or you can build
this project directly and incorporate your changes from the start.

```
export platform=debian13

docker build --target=prod-image -t hardened-php-nginx:8.3-${platform}-fpm php-fpm/8.3/${platform}/

docker build --target=dev-image -t hardened-php-nginx:8.3-${platform}-dev php-fpm/8.3/${platform}/
```

Process to updated extension dependencies JSON
```
export platform=apline3.22

docker build --target=builder --progress=plain -t hardened-php-nginx:8.3-${platform}-builder -f php-fpm/8.3/${platform}/Dockerfile php-fpm/8.3/${platform}/

docker run --name=builder hardened-php-nginx:8.3-${platform}-builder

docker cp builder:/php-ext-deps.txt ./php-ext-deps.txt

cat ./php-ext-deps.txt

docker rm builder
```
