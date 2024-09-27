# recommendations

Recommendations backend for the [eLife API](https://github.com/elifesciences/api-raml).

Implements `/recommendations`-based paths.

## Docker

The project provides an images that runs as the `www-data` user.

Build image(s) with:

```
docker-compose build
```

Run a shell as `www-data` inside a new container:

```
docker-compose run fpm bash
```

Run a PHP interpreter:

```
docker-compose run fpm php ...
```

Run all project tests including PHPUnit:

```
docker-compose -f docker-compose.yml -f docker-compose.ci.yml build
docker-compose -f docker-compose.yml -f docker-compose.ci.yml run ci
```

Run a smoke test:

```
docker-compose up -d
curl -v localhost:8080/ping
```

## Dependencies

To modify dependencies we need to use v1. To switch to v1 from v2 use the following commands:

```
composer --version
composer self-update --1
```

To revert back to current version

```
composer self-update --rollback
composer --version
```
