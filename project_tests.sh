#!/usr/bin/env bash
set -e

env=${1:-ci}

rm -f build/*.xml
proofreader src/ web/
proofreader --no-phpcpd test/
vendor/bin/phpunit --log-junit build/phpunit.xml
