<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service\Storage;

use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\SqlitePDOContainer;
use WebServCo\AccessControl\Model\GrantType;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOUserGrantStorage;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

#[CoversClass(AbstractPDOStorage::class)]
#[CoversClass(PDOUserGrantStorage::class)]
final class PDOUserGrantStorageTest extends TestCase
{
    public function testAKeyIsGrantedOnceAndStoredInUtc(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $storage = $this->createStorage($pdoContainer, GrantType::Role);
        $whenAdded = new DateTimeImmutable('2026-11-18 11:30:00 Europe/Budapest');

        self::assertFalse($storage->has('1', 'admin'));
        self::assertTrue($storage->insert('1', 'admin', $whenAdded));
        self::assertFalse($storage->insert('1', 'admin', $whenAdded));
        self::assertTrue($storage->has('1', 'admin'));
        self::assertSame(
            [['user_id' => '1', 'role_key' => 'admin', 'when_added' => '2026-11-18 10:30:00']],
            $pdoContainer->fetchRows('access_user_role'),
        );
    }

    public function testADeleteSaysWhetherTheUserHadTheKey(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer(), GrantType::Feature);
        $storage->insert('1', 'v3_access', new DateTimeImmutable());

        self::assertTrue($storage->delete('1', 'v3_access'));
        self::assertFalse($storage->delete('1', 'v3_access'));
        self::assertFalse($storage->has('1', 'v3_access'));
    }

    public function testTheUsersOfAKeyComeInBinaryOrder(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer(), GrantType::Feature);

        foreach (['b', '2', 'B', '10', 'a'] as $userId) {
            $storage->insert($userId, 'v3_access', new DateTimeImmutable());
        }

        $storage->insert('3', 'beta', new DateTimeImmutable());

        self::assertSame(['10', '2', 'B', 'a', 'b'], $storage->fetchUserIds('v3_access'));
        self::assertSame([], $storage->fetchUserIds('missing'));
    }

    public function testDeletingAUserDeletesOnlyTheirKeysOfThatKind(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $roleStorage = $this->createStorage($pdoContainer, GrantType::Role);
        $featureStorage = $this->createStorage($pdoContainer, GrantType::Feature);
        $roleStorage->insert('1', 'admin', new DateTimeImmutable());
        $roleStorage->insert('1', 'editor', new DateTimeImmutable());
        $roleStorage->insert('2', 'admin', new DateTimeImmutable());
        $featureStorage->insert('1', 'v3_access', new DateTimeImmutable());

        self::assertTrue($roleStorage->deleteUser('1'));
        self::assertFalse($roleStorage->deleteUser('1'));
        self::assertSame(['2'], $roleStorage->fetchUserIds('admin'));
        self::assertTrue($featureStorage->has('1', 'v3_access'));
    }

    public function testRolesAndFeatureFlagsAreApart(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $this->createStorage($pdoContainer, GrantType::Role)->insert('1', 'admin', new DateTimeImmutable());

        self::assertFalse($this->createStorage($pdoContainer, GrantType::Feature)->has('1', 'admin'));
    }

    public function testTheTablesCanBeInANamedDatabase(): void
    {
        $pdoContainer = new SqlitePDOContainer('oc_v3_ogger');
        $storage = $this->createStorage($pdoContainer, GrantType::Role, 'oc_v3_ogger');

        self::assertTrue($storage->insert('1', 'admin', new DateTimeImmutable()));
        self::assertTrue($storage->has('1', 'admin'));
        self::assertSame(['1'], $storage->fetchUserIds('admin'));
        self::assertCount(1, $pdoContainer->fetchRows('access_user_role'));
        self::assertSame([], $pdoContainer->fetchMainRows('access_user_role'));
        self::assertTrue($storage->delete('1', 'admin'));
        self::assertFalse($storage->deleteUser('1'));
    }

    public function testADatabaseErrorIsNotTakenForAnExistingRow(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer(), GrantType::Role, 'missing_database');

        $this->expectException(PDOException::class);

        $storage->insert('1', 'admin', new DateTimeImmutable());
    }

    public function testAnInvalidDatabaseNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid database name.');

        $this->createStorage(new SqlitePDOContainer(), GrantType::Role, 'oc; DROP TABLE x');
    }

    private function createStorage(
        SqlitePDOContainer $pdoContainer,
        GrantType $grantType,
        ?string $databaseName = null,
    ): PDOUserGrantStorage {
        return new PDOUserGrantStorage(
            (new DataExtractionContainerFactory())->createDataExtractionContainer(false),
            $pdoContainer,
            $grantType,
            $databaseName,
        );
    }
}
