<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service\Storage;

use DateTimeImmutable;
use Override;
use WebServCo\AccessControl\Contract\Storage\UserGrantStorageInterface;
use WebServCo\AccessControl\Model\GrantType;
use WebServCo\Data\Contract\Extraction\DataExtractionContainerInterface;
use WebServCo\Database\Contract\PDOContainerInterface;

use function sprintf;

/**
 * `access_user_role` or `access_user_feature`, by the grant type.
 */
final class PDOUserGrantStorage extends AbstractPDOStorage implements UserGrantStorageInterface
{
    private readonly string $keyColumn;

    private readonly string $table;

    public function __construct(
        DataExtractionContainerInterface $dataExtractionContainer,
        PDOContainerInterface $pdoContainer,
        GrantType $grantType,
        ?string $databaseName = null,
    ) {
        parent::__construct($dataExtractionContainer, $pdoContainer, $databaseName);

        [$tableName, $this->keyColumn] = match ($grantType) {
            GrantType::Feature => ['access_user_feature', 'feature_key'],
            GrantType::Role => ['access_user_role', 'role_key'],
        };
        $this->table = $this->getTableName($tableName);
    }

    #[Override]
    public function delete(string $userId, string $key): bool
    {
        return $this->execute(
            sprintf('DELETE FROM %s WHERE user_id = ? AND %s = ?', $this->table, $this->keyColumn),
            [$userId, $key],
        )->rowCount() === 1;
    }

    #[Override]
    public function deleteUser(string $userId): bool
    {
        return $this->execute(sprintf('DELETE FROM %s WHERE user_id = ?', $this->table), [$userId])->rowCount() > 0;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function fetchUserIds(string $key): array
    {
        $statement = $this->execute(
            sprintf('SELECT user_id FROM %s WHERE %s = ? ORDER BY user_id', $this->table, $this->keyColumn),
            [$key],
        );
        $extraction = $this->dataExtractionContainer->getStrictArrayDataExtractionService();
        $userIds = [];
        $row = $this->fetchRow($statement);

        while ($row !== []) {
            $userIds[] = $extraction->getString($row, 'user_id');
            $row = $this->fetchRow($statement);
        }

        return $userIds;
    }

    #[Override]
    public function has(string $userId, string $key): bool
    {
        return $this->fetchRow(
            $this->execute(
                sprintf('SELECT user_id FROM %s WHERE user_id = ? AND %s = ?', $this->table, $this->keyColumn),
                [$userId, $key],
            ),
        ) !== [];
    }

    #[Override]
    public function insert(string $userId, string $key, DateTimeImmutable $whenAdded): bool
    {
        return $this->insertUnlessExists(
            sprintf('INSERT INTO %s (user_id, %s, when_added) VALUES (?, ?, ?)', $this->table, $this->keyColumn),
            [$userId, $key, $this->formatDateTime($whenAdded)],
        );
    }
}
