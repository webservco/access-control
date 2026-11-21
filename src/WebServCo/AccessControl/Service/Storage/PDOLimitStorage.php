<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service\Storage;

use Override;
use PDOStatement;
use WebServCo\AccessControl\Contract\Storage\LimitStorageInterface;
use WebServCo\AccessControl\Model\Period;

use function sprintf;

/**
 * `access_level`, `access_level_limit`, `access_user_level` and `access_user_limit`.
 *
 * A level or an override is set with an INSERT, and an UPDATE when the row exists, which works the same in MySQL and
 * SQLite. The INSERT of a level that doesn't exist breaks the foreign key, which would also look like an existing
 * row: the service checks the level first.
 */
final class PDOLimitStorage extends AbstractPDOStorage implements LimitStorageInterface
{
    #[Override]
    public function deleteUser(string $userId): bool
    {
        $deletedLevel = $this->execute(
            sprintf('DELETE FROM %s WHERE user_id = ?', $this->getTableName('access_user_level')),
            [$userId],
        )->rowCount() > 0;
        $deletedLimits = $this->execute(
            sprintf('DELETE FROM %s WHERE user_id = ?', $this->getTableName('access_user_limit')),
            [$userId],
        )->rowCount() > 0;

        return $deletedLevel || $deletedLimits;
    }

    #[Override]
    public function deleteUserLimit(string $userId, string $limitKey, Period $period): bool
    {
        return $this->execute(
            sprintf(
                'DELETE FROM %s WHERE user_id = ? AND limit_key = ? AND period = ?',
                $this->getTableName('access_user_limit'),
            ),
            [$userId, $limitKey, $period->value],
        )->rowCount() === 1;
    }

    /**
     * @return array<string,int>
     */
    #[Override]
    public function fetchLevelLimit(string $userId, string $limitKey): array
    {
        return $this->fetchMaxValues(
            $this->execute(
                sprintf(
                    'SELECT period, max_value FROM %s WHERE limit_key = ?'
                    . " AND level_key = COALESCE((SELECT level_key FROM %s WHERE user_id = ?), 'default')",
                    $this->getTableName('access_level_limit'),
                    $this->getTableName('access_user_level'),
                ),
                [$limitKey, $userId],
            ),
        );
    }

    /**
     * @return array<string,int>
     */
    #[Override]
    public function fetchUserLimit(string $userId, string $limitKey): array
    {
        return $this->fetchMaxValues(
            $this->execute(
                sprintf(
                    'SELECT period, max_value FROM %s WHERE user_id = ? AND limit_key = ?',
                    $this->getTableName('access_user_limit'),
                ),
                [$userId, $limitKey],
            ),
        );
    }

    #[Override]
    public function levelExists(string $levelKey): bool
    {
        return $this->fetchRow(
            $this->execute(
                sprintf('SELECT level_key FROM %s WHERE level_key = ?', $this->getTableName('access_level')),
                [$levelKey],
            ),
        ) !== [];
    }

    #[Override]
    public function setUserLevel(string $userId, string $levelKey): bool
    {
        $table = $this->getTableName('access_user_level');

        $inserted = $this->insertUnlessExists(
            sprintf('INSERT INTO %s (user_id, level_key) VALUES (?, ?)', $table),
            [$userId, $levelKey],
        );

        if (!$inserted) {
            $this->execute(sprintf('UPDATE %s SET level_key = ? WHERE user_id = ?', $table), [$levelKey, $userId]);
        }

        return true;
    }

    #[Override]
    public function setUserLimit(string $userId, string $limitKey, Period $period, int $maxValue): bool
    {
        $table = $this->getTableName('access_user_limit');

        $inserted = $this->insertUnlessExists(
            sprintf('INSERT INTO %s (user_id, limit_key, period, max_value) VALUES (?, ?, ?, ?)', $table),
            [$userId, $limitKey, $period->value, $maxValue],
        );

        if (!$inserted) {
            $this->execute(
                sprintf('UPDATE %s SET max_value = ? WHERE user_id = ? AND limit_key = ? AND period = ?', $table),
                [$maxValue, $userId, $limitKey, $period->value],
            );
        }

        return true;
    }

    /**
     * @return array<string,int> by period value
     */
    private function fetchMaxValues(PDOStatement $statement): array
    {
        $extraction = $this->dataExtractionContainer->getStrictArrayDataExtractionService();
        $maxValues = [];
        $row = $this->fetchRow($statement);

        while ($row !== []) {
            $maxValues[$extraction->getString($row, 'period')] = $extraction->getInt($row, 'max_value');
            $row = $this->fetchRow($statement);
        }

        return $maxValues;
    }
}
