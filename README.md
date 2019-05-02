# annotations
Annotations service backend

## Docker

The project provides two images (a `fpm` and a `cli` one) that run as the `www-data` user. They work in concert with others to allow to run the service locally, or to run tasks such as tests.

Build images with:

```
docker-compose build
```

Run a shell as `www-data` inside a new `cli` container:

```
docker-compose run cli /bin/bash
```

Run composer:

```
docker-compose run composer composer install
```

Run a PHP interpreter:

```
docker-compose run cli /usr/bin/env php ...
```

Run PHPUnit:

```
docker-compose run cli vendor/bin/phpunit
```

Run all project tests:

```
docker-compose -f docker-compose.yml -f docker-compose.ci.yml build
docker-compose -f docker-compose.yml -f docker-compose.ci.yml run ci
```

## Journey of a request to the annotations api
1. Query received via the `annotations` api.
1. Retrieve annotation listing from `hypothes.is/api` based on query parameters.
1. Denormalize the annotations into an [object model](src/HypothesisClient/Model/Annotation.php).
1. Normalize the annotations to prepare the response which conforms to the eLife json schema. We rely on [CommonMark](http://commonmark.thephpleague.com/) to step through the Markdown content and prepare the structure that we need, sanitizing each HTML value as it is rendered with [HTMLPurifier](http://htmlpurifier.org/).
1. Return the response to the client.
