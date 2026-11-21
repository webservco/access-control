<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service\Storage;

use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\SqlitePDOContainer;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

use function array_map;
use function array_values;

#[CoversClass(AbstractPDOStorage::class)]
#[CoversClass(PDOUsageCounterStorage::class)]
#[UsesClass(UsageCounterKey::class)]
final class PDOUsageCounterStorageTest extends TestCase
{
    public function testACounterStartsAtZeroAndCounts(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $storage = $this->createStorage($pdoContainer);
        $key = $this->createKey('1', Period::Week, '2026-11-16 00:00:00 UTC');

        self::assertSame(0, $storage->fetchUsedValue($key));
        self::assertTrue($storage->ensureCounter($key));
        self::assertSame(0, $storage->fetchUsedValue($key));
        self::assertSame(2, $storage->addToCounter($key, 2));
        self::assertSame(5, $storage->addToCounter($key, 3));

        // An existing counter keeps its value.
        self::assertTrue($storage->ensureCounter($key));
        self::assertSame(5, $storage->fetchUsedValue($key));
        // In the order of the columns.
        self::assertSame(
            [['1', 'sync', 'week', '2026-11-16 00:00:00', 5]],
            array_map(array_values(...), $pdoContainer->fetchRows('access_usage_counter')),
        );
    }

    public function testEachUserPeriodAndWindowHasItsOwnCounter(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer());
        $keys = [
            $this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC'),
            $this->createKey('1', Period::Day, '2026-11-19 00:00:00 UTC'),
            $this->createKey('1', Period::Week, '2026-11-16 00:00:00 UTC'),
            $this->createKey('2', Period::Day, '2026-11-18 00:00:00 UTC'),
        ];

        foreach ($keys as $index => $key) {
            $storage->ensureCounter($key);
            $storage->addToCounter($key, $index + 1);
        }

        foreach ($keys as $index => $key) {
            self::assertSame($index + 1, $storage->fetchUsedValue($key));
        }
    }

    public function testTheWindowIsStoredInUtc(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer());
        $storage->ensureCounter($this->createKey('1', Period::Day, '2026-11-18 01:00:00 Europe/Budapest'));
        $storage->addToCounter($this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC'), 1);

        self::assertSame(1, $storage->fetchUsedValue($this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC')));
    }

    public function testOldWindowsOfOnePeriodAreDeleted(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $storage = $this->createStorage($pdoContainer);
        $storage->ensureCounter($this->createKey('1', Period::Day, '2026-11-17 00:00:00 UTC'));
        $storage->ensureCounter($this->createKey('2', Period::Day, '2026-11-17 00:00:00 UTC'));
        $storage->ensureCounter($this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC'));
        $storage->ensureCounter($this->createKey('1', Period::Week, '2026-11-09 00:00:00 UTC'));

        self::assertSame(
            2,
            $storage->deleteCountersBefore(Period::Day, new DateTimeImmutable('2026-11-18 00:00:00 UTC')),
        );
        self::assertCount(2, $pdoContainer->fetchRows('access_usage_counter'));
        self::assertSame(
            0,
            $storage->deleteCountersBefore(Period::Day, new DateTimeImmutable('2026-11-18 00:00:00 UTC')),
        );
    }

    public function testDeletingAUserDeletesTheirCounters(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer());
        $storage->ensureCounter($this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC'));
        $storage->ensureCounter($this->createKey('1', Period::Week, '2026-11-16 00:00:00 UTC'));
        $storage->ensureCounter($this->createKey('2', Period::Day, '2026-11-18 00:00:00 UTC'));
        $storage->addToCounter($this->createKey('2', Period::Day, '2026-11-18 00:00:00 UTC'), 4);

        self::assertTrue($storage->deleteUser('1'));
        self::assertFalse($storage->deleteUser('1'));
        self::assertSame(4, $storage->fetchUsedValue($this->createKey('2', Period::Day, '2026-11-18 00:00:00 UTC')));
    }

    public function testTheTableCanBeInANamedDatabase(): void
    {
        $pdoContainer = new SqlitePDOContainer('oc_v3_ogger');
        $storage = $this->createStorage($pdoContainer, 'oc_v3_ogger');
        $key = $this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC');
        $storage->ensureCounter($key);

        self::assertSame(1, $storage->addToCounter($key, 1));
        self::assertSame(1, $storage->fetchUsedValue($key));
        self::assertCount(1, $pdoContainer->fetchRows('access_usage_counter'));
        self::assertSame([], $pdoContainer->fetchMainRows('access_usage_counter'));
        self::assertSame(1, $storage->deleteCountersBefore(Period::Day, new DateTimeImmutable('2026-11-19 UTC')));
        self::assertFalse($storage->deleteUser('1'));
    }

    public function testADatabaseErrorIsNotTakenForAnExistingCounter(): void
    {
        $storage = $this->createStorage(new SqlitePDOContainer(), 'missing_database');

        $this->expectException(PDOException::class);

        $storage->ensureCounter($this->createKey('1', Period::Day, '2026-11-18 00:00:00 UTC'));
    }

    private function createKey(string $userId, Period $period, string $windowStart): UsageCounterKey
    {
        return new UsageCounterKey($userId, 'sync', $period, new DateTimeImmutable($windowStart));
    }

    private function createStorage(
        SqlitePDOContainer $pdoContainer,
        ?string $databaseName = null,
    ): PDOUsageCounterStorage {
        return new PDOUsageCounterStorage(
            (new DataExtractionContainerFactory())->createDataExtractionContainer(false),
            $pdoContainer,
            $databaseName,
        );
    }
}
