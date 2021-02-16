#!/usr/bin/env bash
set -e

env=${1:-ci}

rm -f build/*.xml
vendor/bin/phpcs --standard=phpcs.xml.dist --warning-severity=0 -p src/ web/
vendor/bin/phpcs --standard=phpcs.xml.dist --warning-severity=0 -p test/
vendor/bin/phpunit --log-junit build/phpunit.xml
