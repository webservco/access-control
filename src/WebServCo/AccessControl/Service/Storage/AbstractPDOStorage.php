<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service\Storage;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use WebServCo\Data\Contract\Extraction\DataExtractionContainerInterface;
use WebServCo\Database\Contract\PDOContainerInterface;

use function is_int;
use function preg_match;
use function sprintf;

/**
 * Tables: `resources/access-control.sql`. The names are fixed; the database name, when given, goes into the SQL, so
 * it is validated. Values are always bound, integers as integers.
 */
abstract class AbstractPDOStorage
{
    // A DATETIME column, in UTC.
    protected const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        protected readonly DataExtractionContainerInterface $dataExtractionContainer,
        protected readonly PDOContainerInterface $pdoContainer,
        private readonly ?string $databaseName = null,
    ) {
        if ($databaseName !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $databaseName) !== 1) {
            throw new InvalidArgumentException('Invalid database name.');
        }
    }

    /**
     * @param list<int|string> $parameters
     */
    protected function execute(string $query, array $parameters): PDOStatement
    {
        $statement = $this->pdoContainer->getPDOService()->prepareStatement($this->pdoContainer->getPDO(), $query);

        foreach ($parameters as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @return array<string,scalar|null> empty when there is no row
     */
    protected function fetchRow(PDOStatement $statement): array
    {
        return $this->pdoContainer->getPDOService()->fetchAssoc($statement);
    }

    protected function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_TIME_FORMAT);
    }

    protected function getTableName(string $tableName): string
    {
        return $this->databaseName === null
            ? $tableName
            : sprintf('%s.%s', $this->databaseName, $tableName);
    }

    /**
     * Runs an INSERT. False when the row exists already.
     *
     * SQLSTATE 23000 is any integrity constraint violation (MySQL and SQLite alike), so only use it for a table where
     * the key is the only constraint the insert can break.
     *
     * @param list<int|string> $parameters
     */
    protected function insertUnlessExists(string $query, array $parameters): bool
    {
        try {
            $this->execute($query, $parameters);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return false;
        }

        return true;
    }
}
