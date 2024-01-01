#!/bin/bash

set -e -o pipefail

cd "$(dirname "$0")"

## Make sure docker is running
if ! docker container inspect sqs-queue-processor-unit-tests > /dev/null 2>&1; then
	echo "Starting sqs-queue-processor-unit-tests docker container."
	docker run -d --rm -ti --name sqs-queue-processor-unit-tests -p 4566:4566 -p 4510-4559:4510-4559 localstack/localstack

	# Wait a little to let it start up
	sleep 5
fi

../vendor/bin/phpunit $@
