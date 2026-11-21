<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\SqlitePDOContainer;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

#[CoversClass(AbstractPDOStorage::class)]
#[CoversClass(PDOLimitStorage::class)]
final class PDOLimitStorageTest extends TestCase
{
    public function testAUserWithoutALevelHasTheDefaultLevelsLimit(): void
    {
        $pdoContainer = $this->createPDOContainer();

        self::assertSame(['day' => 3, 'week' => 5], $this->createStorage($pdoContainer)->fetchLevelLimit('1', 'sync'));
        self::assertSame([], $this->createStorage($pdoContainer)->fetchLevelLimit('1', 'missing'));
    }

    public function testAUserOnALevelHasThatLevelsLimit(): void
    {
        $storage = $this->createStorage($this->createPDOContainer());

        self::assertTrue($storage->setUserLevel('1', 'pro'));
        self::assertSame(['week' => 50], $storage->fetchLevelLimit('1', 'sync'));
        self::assertSame(['day' => 3, 'week' => 5], $storage->fetchLevelLimit('2', 'sync'));

        // The row exists: the level changes.
        self::assertTrue($storage->setUserLevel('1', 'default'));
        self::assertSame(['day' => 3, 'week' => 5], $storage->fetchLevelLimit('1', 'sync'));
    }

    public function testOnlyExistingLevelsExist(): void
    {
        $storage = $this->createStorage($this->createPDOContainer());

        self::assertTrue($storage->levelExists('default'));
        self::assertTrue($storage->levelExists('pro'));
        self::assertFalse($storage->levelExists('Pro'));
    }

    public function testAnOverrideIsSetChangedAndRemoved(): void
    {
        $storage = $this->createStorage($this->createPDOContainer());

        self::assertSame([], $storage->fetchUserLimit('1', 'sync'));
        self::assertTrue($storage->setUserLimit('1', 'sync', Period::Day, 10));
        self::assertTrue($storage->setUserLimit('1', 'sync', Period::Month, 0));
        self::assertTrue($storage->setUserLimit('1', 'sync', Period::Day, 20));
        self::assertSame(['day' => 20, 'month' => 0], $storage->fetchUserLimit('1', 'sync'));
        self::assertSame([], $storage->fetchUserLimit('2', 'sync'));

        self::assertTrue($storage->deleteUserLimit('1', 'sync', Period::Day));
        self::assertFalse($storage->deleteUserLimit('1', 'sync', Period::Day));
        self::assertSame(['month' => 0], $storage->fetchUserLimit('1', 'sync'));
    }

    public function testDeletingAUserDeletesTheirLevelAndOverrides(): void
    {
        $pdoContainer = $this->createPDOContainer();
        $storage = $this->createStorage($pdoContainer);
        $storage->setUserLevel('1', 'pro');
        $storage->setUserLimit('1', 'sync', Period::Day, 10);
        $storage->setUserLimit('2', 'sync', Period::Day, 10);

        self::assertTrue($storage->deleteUser('1'));
        self::assertFalse($storage->deleteUser('1'));
        self::assertSame([], $pdoContainer->fetchRows('access_user_level'));
        self::assertSame(['day' => 10], $storage->fetchUserLimit('2', 'sync'));

        // Only an override, or only a level.
        $storage->setUserLimit('1', 'sync', Period::Day, 10);
        self::assertTrue($storage->deleteUser('1'));
        $storage->setUserLevel('1', 'pro');
        self::assertTrue($storage->deleteUser('1'));
    }

    public function testTheTablesCanBeInANamedDatabase(): void
    {
        $pdoContainer = $this->createPDOContainer('oc_v3_ogger');
        $storage = $this->createStorage($pdoContainer, 'oc_v3_ogger');
        $storage->setUserLevel('1', 'pro');
        $storage->setUserLimit('1', 'sync', Period::Day, 10);
        $storage->setUserLevel('1', 'pro');
        $storage->setUserLimit('1', 'sync', Period::Day, 10);

        self::assertSame([], $pdoContainer->fetchMainRows('access_user_level'));
        self::assertSame([], $pdoContainer->fetchMainRows('access_user_limit'));
        self::assertSame(['week' => 50], $storage->fetchLevelLimit('1', 'sync'));
        self::assertSame(['day' => 10], $storage->fetchUserLimit('1', 'sync'));
        self::assertTrue($storage->levelExists('pro'));
        self::assertTrue($storage->deleteUserLimit('1', 'sync', Period::Day));
        self::assertTrue($storage->deleteUser('1'));
    }

    /**
     * `default`: 3 a day and 5 a week; `pro`: 50 a week.
     */
    private function createPDOContainer(?string $databaseName = null): SqlitePDOContainer
    {
        $pdoContainer = new SqlitePDOContainer($databaseName);
        $pdoContainer->insertLevel('pro');
        $pdoContainer->insertLevelLimit('default', 'sync', 'day', 3);
        $pdoContainer->insertLevelLimit('default', 'sync', 'week', 5);
        $pdoContainer->insertLevelLimit('pro', 'sync', 'week', 50);
        $pdoContainer->insertLevelLimit('pro', 'other', 'day', 1);

        return $pdoContainer;
    }

    private function createStorage(SqlitePDOContainer $pdoContainer, ?string $databaseName = null): PDOLimitStorage
    {
        return new PDOLimitStorage(
            (new DataExtractionContainerFactory())->createDataExtractionContainer(false),
            $pdoContainer,
            $databaseName,
        );
    }
}
