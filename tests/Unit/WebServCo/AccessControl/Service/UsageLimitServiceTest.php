<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\UsageLimitFixture;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\AccessControl\Service\UsageCounterService;
use WebServCo\AccessControl\Service\UsageLimitService;
use WebServCo\AccessControl\Service\UsageResultService;

use function array_column;

/**
 * Limits and counting. The fixture's clock starts on Wednesday 2026-11-18 at 10:00 UTC.
 */
#[CoversClass(UsageCounterService::class)]
#[CoversClass(UsageLimitService::class)]
#[UsesClass(AbstractPDOStorage::class)]
#[UsesClass(InputValidator::class)]
#[UsesClass(PDOLimitStorage::class)]
#[UsesClass(PDOUsageCounterStorage::class)]
#[UsesClass(PeriodLimit::class)]
#[UsesClass(PeriodUsage::class)]
#[UsesClass(PeriodWindowService::class)]
#[UsesClass(UsageCounterKey::class)]
#[UsesClass(UsageResult::class)]
#[UsesClass(UsageResultService::class)]
final class UsageLimitServiceTest extends TestCase
{
    public function testTheLevelsLimitIsCountedUntilAPeriodIsFull(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3, 'week' => 5]);

        foreach ([1, 2, 3] as $usedValue) {
            $result = $fixture->getService()->consume('1', 'sync');

            self::assertTrue($result->isAllowed);
            self::assertSame([$usedValue, $usedValue], array_column($result->periodUsages, 'usedValue'));
        }

        $result = $fixture->getService()->consume('1', 'sync');

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Day, $result->decidingPeriodUsage->period);
        self::assertSame('2026-11-19 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
        self::assertSame([3, 3], array_column($result->periodUsages, 'usedValue'));
        self::assertSame([0, 2], array_column($result->periodUsages, 'leftValue'));
        self::assertSame([3, 3], $fixture->fetchUsedValues());
    }

    public function testAnotherUserOrKeyHasItsOwnCount(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 1]);
        $fixture->getPDOContainer()->insertLevelLimit('default', 'export', 'day', 1);

        self::assertTrue($fixture->getService()->consume('1', 'sync')->isAllowed);
        self::assertTrue($fixture->getService()->consume('2', 'sync')->isAllowed);
        self::assertTrue($fixture->getService()->consume('1', 'export')->isAllowed);
        self::assertFalse($fixture->getService()->consume('1', 'sync')->isAllowed);
    }

    public function testAFullPeriodResetsWhenTheNextWindowStarts(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['week' => 2]);
        $fixture->getClock()->setNow('2026-11-22 23:59:59 UTC');
        $fixture->getService()->consume('1', 'sync', 2);

        self::assertFalse($fixture->getService()->consume('1', 'sync')->isAllowed);

        $fixture->getClock()->setNow('2026-11-23 00:00:00 UTC');
        $result = $fixture->getService()->consume('1', 'sync');

        self::assertTrue($result->isAllowed);
        self::assertSame(1, $result->decidingPeriodUsage->usedValue);
        self::assertSame('2026-11-30 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
    }

    public function testARefusalRollsBackTheOtherPeriods(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 10, 'week' => 3]);

        self::assertTrue($fixture->getService()->consume('1', 'sync', 3)->isAllowed);

        $result = $fixture->getService()->consume('1', 'sync');

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
        self::assertSame('2026-11-23 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
        // The day had room, but its unit was rolled back with the week's.
        self::assertSame([3, 3], $fixture->fetchUsedValues());
        self::assertFalse($fixture->getPDOContainer()->getPDO()->inTransaction());
    }

    public function testWhenEveryPeriodIsFullThePeriodThatResetsLastDecides(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 2, 'week' => 2, 'month' => 2]);
        $fixture->getService()->consume('1', 'sync', 2);
        $result = $fixture->getService()->check('1', 'sync');

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Month, $result->decidingPeriodUsage->period);
        self::assertSame('2026-12-01 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
    }

    public function testCheckCountsNothing(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 1]);

        self::assertTrue($fixture->getService()->check('1', 'sync')->isAllowed);
        // Still allowed: the first check counted nothing.
        self::assertTrue($fixture->getService()->check('1', 'sync', 1)->isAllowed);
        self::assertFalse($fixture->getService()->check('1', 'sync', 2)->isAllowed);
        self::assertSame([], $fixture->getPDOContainer()->fetchRows('access_usage_counter'));

        $fixture->getService()->consume('1', 'sync');
        $result = $fixture->getService()->check('1', 'sync');

        self::assertFalse($result->isAllowed);
        self::assertSame(1, $result->decidingPeriodUsage->usedValue);
    }

    public function testTheUsersLevelAndOverridesMakeTheirLimit(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3, 'week' => 5]);
        $fixture->getPDOContainer()->insertLevel('pro');
        $fixture->getPDOContainer()->insertLevelLimit('pro', 'sync', 'week', 50);

        self::assertTrue($fixture->getService()->setUserLevel('1', 'pro'));
        self::assertSame([[Period::Week, 50]], $this->fetchMaxValues($fixture, '1'));

        // An override adds a period, or replaces the level's.
        self::assertTrue($fixture->getService()->setUserLimit('1', 'sync', Period::Day, 10));
        self::assertTrue($fixture->getService()->setUserLimit('1', 'sync', Period::Week, 7));
        self::assertSame([[Period::Day, 10], [Period::Week, 7]], $this->fetchMaxValues($fixture, '1'));

        // Null removes it.
        self::assertTrue($fixture->getService()->setUserLimit('1', 'sync', Period::Week, null));
        self::assertFalse($fixture->getService()->setUserLimit('1', 'sync', Period::Week, null));
        self::assertSame([[Period::Day, 10], [Period::Week, 50]], $this->fetchMaxValues($fixture, '1'));

        // Other users stay on the default level.
        self::assertSame([[Period::Day, 3], [Period::Week, 5]], $this->fetchMaxValues($fixture, '2'));
    }

    public function testAnOverrideWorksForALimitTheLevelDoesNotHave(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->getService()->setUserLimit('1', 'export', Period::Month, 1);

        self::assertTrue($fixture->getService()->consume('1', 'export')->isAllowed);
        self::assertFalse($fixture->getService()->consume('1', 'export')->isAllowed);
    }

    public function testAMaximumOfZeroBlocks(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3, 'week' => 5]);
        $fixture->getService()->setUserLimit('1', 'sync', Period::Week, 0);
        $result = $fixture->getService()->consume('1', 'sync');

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
        self::assertSame([0, 0], $fixture->fetchUsedValues());
        self::assertFalse($fixture->getService()->check('1', 'sync')->isAllowed);
    }

    public function testALimitKeyWithoutALimitIsAnError(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3]);

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageIs('No limit "synk" for the user: none for the level, and no override.');

        $fixture->getService()->consume('1', 'synk');
    }

    public function testCheckOfALimitKeyWithoutALimitIsAnError(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(OutOfBoundsException::class);

        $fixture->getService()->check('1', 'sync');
    }

    public function testAnUnknownLevelIsRefused(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageIs('No access level "gold".');

        $fixture->getService()->setUserLevel('1', 'gold');
    }

    public function testAnInvalidAmountIsRefused(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(InvalidArgumentException::class);

        $fixture->getService()->consume('1', 'sync', 0);
    }

    public function testAnInvalidMaximumIsRefused(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(InvalidArgumentException::class);

        $fixture->getService()->setUserLimit('1', 'sync', Period::Day, -1);
    }

    public function testAnInvalidLevelKeyIsRefused(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(InvalidArgumentException::class);

        $fixture->getService()->setUserLevel('1', 'Pro');
    }

    /**
     * @return list<array{\WebServCo\AccessControl\Model\Period,int}>
     */
    private function fetchMaxValues(UsageLimitFixture $fixture, string $userId): array
    {
        $maxValues = [];

        foreach ($fixture->getService()->check($userId, 'sync')->periodUsages as $periodUsage) {
            $maxValues[] = [$periodUsage->period, $periodUsage->maxValue];
        }

        return $maxValues;
    }
}
