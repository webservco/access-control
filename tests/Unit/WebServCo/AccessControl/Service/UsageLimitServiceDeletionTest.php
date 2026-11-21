<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use DateTimeImmutable;
use InvalidArgumentException;
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

/**
 * Old counters and deleted users. The fixture's clock starts on Wednesday 2026-11-18 at 10:00 UTC.
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
final class UsageLimitServiceDeletionTest extends TestCase
{
    public function testOldCountersAreDeletedAndRunningWindowsKept(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 9, 'week' => 9, 'month' => 9]);

        foreach (['2026-10-15 10:00:00 UTC', '2026-11-10 10:00:00 UTC', '2026-11-18 10:00:00 UTC'] as $now) {
            $fixture->getClock()->setNow($now);
            $fixture->getService()->consume('1', 'sync');
        }

        // 8 counters: a day, a week and a month for 2026-10-15 and for 2026-11-10; a day and a week for 2026-11-18,
        // whose month is 2026-11-10's.
        self::assertCount(8, $fixture->getPDOContainer()->fetchRows('access_usage_counter'));

        // A date in the future: only the windows that ended by now.
        self::assertSame(
            5,
            $fixture->getService()->deleteCountersBefore(new DateTimeImmutable('2027-06-01 00:00:00 UTC')),
        );
        self::assertSame(
            [
                ['day', '2026-11-18 00:00:00', 1],
                ['month', '2026-11-01 00:00:00', 2],
                ['week', '2026-11-16 00:00:00', 1],
            ],
            $fixture->fetchCounters(),
        );
        self::assertSame(
            0,
            $fixture->getService()->deleteCountersBefore(new DateTimeImmutable('2026-11-18 UTC')),
        );
    }

    public function testCountersBeforeADateArePartlyDeleted(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 9, 'week' => 9, 'month' => 9]);

        foreach (['2026-10-15 10:00:00 UTC', '2026-11-10 10:00:00 UTC', '2026-11-18 10:00:00 UTC'] as $now) {
            $fixture->getClock()->setNow($now);
            $fixture->getService()->consume('1', 'sync');
        }

        // 2026-11-11 is in the day 2026-11-11, the week 2026-11-09 and the month 2026-11-01. Older windows go: the days
        // 2026-10-15 and 2026-11-10, the week 2026-10-12 and the month 2026-10-01.
        self::assertSame(
            4,
            $fixture->getService()->deleteCountersBefore(new DateTimeImmutable('2026-11-11 12:00:00 UTC')),
        );
        self::assertSame(
            [
                ['day', '2026-11-18 00:00:00', 1],
                ['month', '2026-11-01 00:00:00', 2],
                ['week', '2026-11-09 00:00:00', 1],
                ['week', '2026-11-16 00:00:00', 1],
            ],
            $fixture->fetchCounters(),
        );
    }

    public function testDeletingAUserDeletesTheirLevelOverridesAndCounters(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3]);
        $fixture->getPDOContainer()->insertLevel('pro');
        $fixture->getService()->setUserLevel('1', 'pro');
        $fixture->getService()->setUserLimit('1', 'sync', Period::Day, 5);
        $fixture->getService()->consume('1', 'sync');
        $fixture->getService()->consume('2', 'sync');

        self::assertTrue($fixture->getService()->deleteUser('1'));
        self::assertFalse($fixture->getService()->deleteUser('1'));
        self::assertSame([], $fixture->getPDOContainer()->fetchRows('access_user_level'));
        self::assertSame([], $fixture->getPDOContainer()->fetchRows('access_user_limit'));
        self::assertCount(1, $fixture->getPDOContainer()->fetchRows('access_usage_counter'));

        // Only counters.
        $fixture->getService()->consume('1', 'sync');
        self::assertTrue($fixture->getService()->deleteUser('1'));
    }

    public function testAnInvalidUserIdIsRefusedOnDelete(): void
    {
        $fixture = new UsageLimitFixture();
        $this->expectException(InvalidArgumentException::class);

        $fixture->getService()->deleteUser('');
    }
}
