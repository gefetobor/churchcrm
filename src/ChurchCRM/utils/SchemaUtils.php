<?php

namespace ChurchCRM\Utils;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Idempotent schema helpers for upgrade scripts.
 *
 * MySQL 8 has no "ADD COLUMN IF NOT EXISTS", so upgrade steps that must be
 * safe to re-run (for example on a database that already received the
 * change through Install.sql) check information_schema first.
 */
class SchemaUtils
{
    public static function tableExists(string $table, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getConnection();
        $stmt = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->bindValue(':table', $table);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function columnExists(string $table, string $column, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getConnection();
        $stmt = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->bindValue(':table', $table);
        $stmt->bindValue(':column', $column);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param string $definition column definition after the name, e.g. "datetime DEFAULT NULL AFTER `event_end`"
     */
    public static function addColumnIfMissing(string $table, string $column, string $definition, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getConnection();
        if (self::columnExists($table, $column, $connection)) {
            return false;
        }
        $connection->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));

        return true;
    }

    /**
     * @param string $createSql full CREATE TABLE statement
     */
    public static function createTableIfMissing(string $table, string $createSql, ?ConnectionInterface $connection = null): bool
    {
        $connection ??= Propel::getConnection();
        if (self::tableExists($table, $connection)) {
            return false;
        }
        $connection->exec($createSql);

        return true;
    }
}
