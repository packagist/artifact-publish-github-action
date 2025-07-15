#!/usr/bin/env bash
set -euo pipefail

MINIMUM_REQUIRED_VERSION=${1}

PHP_PATH="$(which php)"

if ! command -v $PHP_PATH 2>&1 >/dev/null
then
    echo "::error ::PHP not found at '${PHP_PATH}'"
    exit 1
fi

PHP_VERSION=$($PHP_PATH -r 'echo phpversion();')

if [ $(printf '%s\n' "${MINIMUM_REQUIRED_VERSION}" "${PHP_VERSION}" | sort -V | head -1) != "${MINIMUM_REQUIRED_VERSION}" ]
then
  echo "::error ::PHP version '${MINIMUM_REQUIRED_VERSION}' or higher is required"
  exit 1
fi
