#!/bin/bash -ex

# update dependencies
PWD=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
BASEDIR=`dirname $PWD`

# db
RDB_DB="${RDB_DB=RQL_TEST_`date +%s`}"
export RDB_DB

# run tests
php $BASEDIR/tests/TestHelpers/createDb.php
ASYNC=yes $BASEDIR/vendor/bin/phpunit -c $PWD/phpunit.xml --colors=always "$@"
php $BASEDIR/tests/TestHelpers/deleteDb.php
php $BASEDIR/tests/TestHelpers/createDb.php
ASYNC=no $BASEDIR/vendor/bin/phpunit -c $PWD/phpunit.xml --colors=always "$@"
STATUS=$?

#remove db
php $BASEDIR/tests/TestHelpers/deleteDb.php

# exit
exit $STATUS
