<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service\Storage;

use DateTimeImmutable;
use Override;
use WebServCo\AccessControl\Contract\Storage\UsageCounterStorageInterface;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\Period;

use function sprintf;

/**
 * `access_usage_counter`.
 */
final class PDOUsageCounterStorage extends AbstractPDOStorage implements UsageCounterStorageInterface
{
    private const string WHERE_COUNTER = 'user_id = ? AND limit_key = ? AND period = ? AND window_start = ?';

    #[Override]
    public function addToCounter(UsageCounterKey $usageCounterKey, int $amount): int
    {
        $this->execute(
            sprintf('UPDATE %s SET used_value = used_value + ? WHERE %s', $this->getTable(), self::WHERE_COUNTER),
            [$amount, ...$this->getKeyParameters($usageCounterKey)],
        );

        return $this->fetchUsedValue($usageCounterKey);
    }

    #[Override]
    public function deleteCountersBefore(Period $period, DateTimeImmutable $windowStart): int
    {
        return $this->execute(
            sprintf('DELETE FROM %s WHERE period = ? AND window_start < ?', $this->getTable()),
            [$period->value, $this->formatDateTime($windowStart)],
        )->rowCount();
    }

    #[Override]
    public function deleteUser(string $userId): bool
    {
        return $this->execute(sprintf('DELETE FROM %s WHERE user_id = ?', $this->getTable()), [$userId])
            ->rowCount() > 0;
    }

    #[Override]
    public function ensureCounter(UsageCounterKey $usageCounterKey): bool
    {
        $this->insertUnlessExists(
            sprintf(
                'INSERT INTO %s (user_id, limit_key, period, window_start, used_value) VALUES (?, ?, ?, ?, 0)',
                $this->getTable(),
            ),
            $this->getKeyParameters($usageCounterKey),
        );

        return true;
    }

    #[Override]
    public function fetchUsedValue(UsageCounterKey $usageCounterKey): int
    {
        $row = $this->fetchRow(
            $this->execute(
                sprintf('SELECT used_value FROM %s WHERE %s', $this->getTable(), self::WHERE_COUNTER),
                $this->getKeyParameters($usageCounterKey),
            ),
        );

        if ($row === []) {
            return 0;
        }

        return $this->dataExtractionContainer->getStrictArrayDataExtractionService()->getInt($row, 'used_value');
    }

    /**
     * @return list<string>
     */
    private function getKeyParameters(UsageCounterKey $usageCounterKey): array
    {
        return [
            $usageCounterKey->userId,
            $usageCounterKey->limitKey,
            $usageCounterKey->period->value,
            $this->formatDateTime($usageCounterKey->windowStart),
        ];
    }

    private function getTable(): string
    {
        return $this->getTableName('access_usage_counter');
    }
}
