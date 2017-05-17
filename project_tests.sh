#!/usr/bin/env bash
set -e

rm -f build/*.xml
proofreader src/ tests/ web/
vendor/bin/phpunit --log-junit build/phpunit.xml
# the api-dummy is currently missing external articles in the related articles of its samples
# they should be added, to get feeback in ci
bin/ci-import ci
