#!/bin/bash
# Configures the slave to replicate from the master.
# Runs as a one-shot container after both master and slave are healthy.
set -e

wait_for_mysql() {
    local HOST="$1"
    echo "Waiting for MySQL on ${HOST}..."
    until MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h "${HOST}" -u root \
          -e "SELECT 1" >/dev/null 2>&1; do
        sleep 3
    done
    echo "MySQL on ${HOST} is ready."
}

wait_for_mysql db-master
wait_for_mysql db-slave

# Idempotency check – skip if replication is already running
SLAVE_IO=$(MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h db-slave -u root \
    -e "SHOW SLAVE STATUS\G" 2>/dev/null | grep "Slave_IO_Running:" | awk '{print $2}')

if [ "${SLAVE_IO}" = "Yes" ]; then
    echo "Replication is already running. Nothing to do."
    exit 0
fi

echo "Configuring replication..."
MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h db-slave -u root <<SQL
STOP SLAVE;
RESET SLAVE ALL;
CHANGE MASTER TO
    MASTER_HOST        = 'db-master',
    MASTER_USER        = 'replicator',
    MASTER_PASSWORD    = '${REPLICATION_PASSWORD}',
    MASTER_AUTO_POSITION = 1;
START SLAVE;
SQL

echo "Replication configured. Checking status..."
MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h db-slave -u root \
    -e "SHOW SLAVE STATUS\G" | grep -E "Slave_IO_Running|Slave_SQL_Running|Last_Error"
