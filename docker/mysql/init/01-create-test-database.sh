#!/bin/sh
# Runs once, on the first start of an empty data directory. A shell script rather than
# plain .sql because the database and user names come from the environment.
set -e

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE}_test
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}_test.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
