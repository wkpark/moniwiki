#!/bin/sh
# Run the PHPUnit test suite from the repository root.
#
# PHPUnit 10 is looked up in this order:
#   $PHPUNIT, phpunit in PATH, vendor/bin/phpunit, tests/phpunit.phar
# Extra arguments are passed to PHPUnit, e.g. sh tests/run.sh --filter Fetch

cd "$(dirname "$0")/.." || exit 1

PHP=${PHP:-php}

if [ -n "$PHPUNIT" ]; then
	set -- $PHPUNIT "$@"
elif command -v phpunit >/dev/null 2>&1; then
	set -- phpunit "$@"
elif [ -x vendor/bin/phpunit ]; then
	set -- vendor/bin/phpunit "$@"
elif [ -f tests/phpunit.phar ]; then
	set -- "$PHP" tests/phpunit.phar "$@"
else
	echo "PHPUnit 10 is not installed." >&2
	echo "Download it into this repository and run again:" >&2
	echo "  wget -O tests/phpunit.phar https://phar.phpunit.de/phpunit-10.phar" >&2
	exit 1
fi

exec "$@"
