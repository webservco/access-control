<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use Override;
use PDO;
use WebServCo\Database\Contract\PDOContainerInterface;
use WebServCo\Database\Contract\PDOServiceInterface;
use WebServCo\Database\Service\PDOService;

use function sprintf;
use function str_replace;

/**
 * In-memory SQLite with the tables of `resources/access-control.sql`, in SQLite syntax. With a database name, the
 * tables are also in an attached database of that name. SQLite finds a table without a database name in the main
 * database first, so a query that leaves the name out uses the main one's tables, and `fetchMainRows()` shows it.
 * PDO is set up like webservco/database's PDOFactory: exceptions, associative fetch, no emulated prepares.
 */
final class SqlitePDOContainer implements PDOContainerInterface
{
    private const string SCHEMA = <<<'SQL'
        CREATE TABLE {db}access_user_role (
            user_id TEXT NOT NULL,
            role_key TEXT NOT NULL,
            when_added TEXT NOT NULL,
            PRIMARY KEY (user_id, role_key)
        );
        CREATE TABLE {db}access_user_feature (
            user_id TEXT NOT NULL,
            feature_key TEXT NOT NULL,
            when_added TEXT NOT NULL,
            PRIMARY KEY (user_id, feature_key)
        );
        CREATE TABLE {db}access_level (
            level_key TEXT NOT NULL PRIMARY KEY
        );
        INSERT INTO {db}access_level (level_key) VALUES ('default');
        CREATE TABLE {db}access_user_level (
            user_id TEXT NOT NULL PRIMARY KEY,
            level_key TEXT NOT NULL REFERENCES access_level (level_key)
        );
        CREATE TABLE {db}access_level_limit (
            level_key TEXT NOT NULL REFERENCES access_level (level_key),
            limit_key TEXT NOT NULL,
            period TEXT NOT NULL CHECK (period IN ('day', 'week', 'month')),
            max_value INTEGER NOT NULL,
            PRIMARY KEY (level_key, limit_key, period)
        );
        CREATE TABLE {db}access_user_limit (
            user_id TEXT NOT NULL,
            limit_key TEXT NOT NULL,
            period TEXT NOT NULL CHECK (period IN ('day', 'week', 'month')),
            max_value INTEGER NOT NULL,
            PRIMARY KEY (user_id, limit_key, period)
        );
        CREATE TABLE {db}access_usage_counter (
            user_id TEXT NOT NULL,
            limit_key TEXT NOT NULL,
            period TEXT NOT NULL CHECK (period IN ('day', 'week', 'month')),
            window_start TEXT NOT NULL,
            used_value INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, limit_key, period, window_start)
        );
        SQL;

    private ?PDO $pdo = null;

    public function __construct(private readonly ?string $databaseName = null)
    {
    }

    #[Override]
    public function createPDO(): PDO
    {
        $pdo = new PDO(
            'sqlite::memory:',
            null,
            null,
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        );
        $pdo->exec('PRAGMA foreign_keys = ON');

        $pdo->exec(str_replace('{db}', '', self::SCHEMA));

        if ($this->databaseName !== null) {
            $pdo->exec(sprintf("ATTACH DATABASE ':memory:' AS %s", $this->databaseName));
            $pdo->exec(str_replace('{db}', $this->databaseName . '.', self::SCHEMA));
        }

        return $pdo;
    }

    /**
     * The rows of the main database's table, which a query without the database name uses.
     *
     * @return list<array<string,scalar|null>>
     */
    public function fetchMainRows(string $tableName): array
    {
        return $this->fetchAll('main.' . $tableName);
    }

    /**
     * The rows of the table, in the named database when there is one, in the order they were added.
     *
     * @return list<array<string,scalar|null>>
     */
    public function fetchRows(string $tableName): array
    {
        return $this->fetchAll($this->table($tableName));
    }

    #[Override]
    public function getPDO(): PDO
    {
        return $this->pdo ??= $this->createPDO();
    }

    #[Override]
    public function getPDOService(): PDOServiceInterface
    {
        return new PDOService();
    }

    public function insertLevel(string $levelKey): bool
    {
        return $this->getPDOService()
            ->prepareStatement(
                $this->getPDO(),
                sprintf('INSERT INTO %s (level_key) VALUES (?)', $this->table('access_level')),
            )
            ->execute([$levelKey]);
    }

    public function insertLevelLimit(string $levelKey, string $limitKey, string $period, int $maxValue): bool
    {
        return $this->getPDOService()
            ->prepareStatement(
                $this->getPDO(),
                sprintf(
                    'INSERT INTO %s (level_key, limit_key, period, max_value) VALUES (?, ?, ?, ?)',
                    $this->table('access_level_limit'),
                ),
            )
            ->execute([$levelKey, $limitKey, $period, $maxValue]);
    }

    /**
     * @return list<array<string,scalar|null>>
     */
    private function fetchAll(string $qualifiedTableName): array
    {
        $statement = $this->getPDOService()->prepareStatement(
            $this->getPDO(),
            sprintf('SELECT * FROM %s ORDER BY rowid', $qualifiedTableName),
        );
        $statement->execute();
        $rows = [];
        $row = $this->getPDOService()->fetchAssoc($statement);

        while ($row !== []) {
            $rows[] = $row;
            $row = $this->getPDOService()->fetchAssoc($statement);
        }

        return $rows;
    }

    private function table(string $tableName): string
    {
        return $this->databaseName === null
            ? $tableName
            : $this->databaseName . '.' . $tableName;
    }
}
