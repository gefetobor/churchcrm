#!/usr/bin/env bash
set -e

APP_DIR="/var/www/html"
SRC_DIR="${APP_DIR}/src"
ORM_DIR="${APP_DIR}/orm"
CONFIG_FILE="${SRC_DIR}/Include/Config.php"

if [ ! -f "${CONFIG_FILE}" ]; then
  cat > "${CONFIG_FILE}" <<PHP
<?php
\$sSERVERNAME = '${DB_SERVER:-mysql}';
\$dbPort = '${DB_PORT:-3306}';
\$sUSER = '${DB_USER:-churchcrm}';
\$sPASSWORD = '${DB_PASSWORD:-churchcrm}';
\$sDATABASE = '${DB_NAME:-churchcrm}';
\$sRootPath = '${APP_ROOT_PATH:-}';
\$bLockURL = false;
\$URL = ['${APP_URL:-http://localhost:8080/}'];
PHP
fi

if [ -d "${SRC_DIR}/vendor" ]; then
  if [ ! -f "${ORM_DIR}/propel.php" ]; then
    cat > "${ORM_DIR}/propel.php" <<PHP
<?php
return [
    'propel' => [
        'paths' => [
            'schemaDir' => __DIR__,
            'phpDir' => __DIR__ . '/../src',
        ],
        'generator' => [
            'schema' => [
                'autoPackage' => true,
            ],
        ],
        'database' => [
            'connections' => [
                'default' => [
                    'adapter' => 'mysql',
                    'dsn' => 'mysql:host=${DB_SERVER:-mysql};port=${DB_PORT:-3306};dbname=${DB_NAME:-churchcrm}',
                    'user' => '${DB_USER:-churchcrm}',
                    'password' => '${DB_PASSWORD:-churchcrm}',
                    'settings' => [
                        'charset' => 'utf8',
                    ],
                ],
            ],
        ],
    ],
];
PHP
  fi

  if [ ! -f "${SRC_DIR}/ChurchCRM/model/ChurchCRM/FirstTimerQuery.php" ] || \
     [ ! -f "${SRC_DIR}/ChurchCRM/model/ChurchCRM/Base/FirstTimer.php" ]; then
    php "${SRC_DIR}/vendor/bin/propel" --config-dir="${ORM_DIR}" model:build || true
  fi
fi

exec "$@"
