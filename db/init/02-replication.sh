#!/bin/bash
# Runs inside the db-master container during first-time initialisation.
# Creates the replication user using the env-var password.
set -e

MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -u root <<SQL
CREATE USER IF NOT EXISTS 'replicator'@'%'
    IDENTIFIED WITH mysql_native_password BY '${REPLICATION_PASSWORD}';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
FLUSH PRIVILEGES;
SQL

echo "Replication user created."
