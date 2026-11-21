<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\UsageResultService;

use function array_column;

#[CoversClass(UsageResultService::class)]
#[UsesClass(PeriodLimit::class)]
#[UsesClass(PeriodUsage::class)]
#[UsesClass(PeriodWindowService::class)]
#[UsesClass(UsageResult::class)]
final class UsageResultServiceTest extends TestCase
{
    // A Wednesday.
    private const string NOW = '2026-11-18 10:00:00 UTC';

    public function testAnAmountThatFitsIsAllowedAndThePeriodWithTheLeastLeftDecides(): void
    {
        $service = $this->createService();
        $result = $service->createCheckResult(
            [$this->createUsage(Period::Day, 5, 1), $this->createUsage(Period::Month, 100, 1)],
            1,
        );

        self::assertTrue($result->isAllowed);
        self::assertSame(Period::Day, $result->decidingPeriodUsage->period);
        self::assertSame(4, $result->decidingPeriodUsage->leftValue);
        self::assertSame('2026-11-19 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
        self::assertSame([Period::Day, Period::Month], array_column($result->periodUsages, 'period'));
        self::assertSame([4, 99], array_column($result->periodUsages, 'leftValue'));
        self::assertEquals(
            [new DateTimeImmutable('2026-11-19 00:00:00 UTC'), new DateTimeImmutable('2026-12-01 00:00:00 UTC')],
            array_column($result->periodUsages, 'resetsAt'),
        );
    }

    public function testBetweenEqualsLeftThePeriodThatResetsLastDecides(): void
    {
        $result = $this->createService()->createCheckResult(
            [$this->createUsage(Period::Day, 3, 0), $this->createUsage(Period::Week, 3, 0)],
            1,
        );

        self::assertTrue($result->isAllowed);
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
    }

    public function testAfterARefusalTheFullPeriodThatResetsLastDecides(): void
    {
        $result = $this->createService()->createCheckResult(
            [
                $this->createUsage(Period::Day, 2, 2),
                $this->createUsage(Period::Week, 2, 2),
                $this->createUsage(Period::Month, 100, 2),
            ],
            1,
        );

        self::assertFalse($result->isAllowed);
        // The month has room: only the full periods count.
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
        self::assertSame(0, $result->decidingPeriodUsage->leftValue);
        self::assertSame('2026-11-23 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
    }

    public function testAnAmountLargerThanWhatIsLeftIsRefused(): void
    {
        $result = $this->createService()->createCheckResult(
            [$this->createUsage(Period::Day, 10, 5), $this->createUsage(Period::Week, 100, 5)],
            6,
        );

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Day, $result->decidingPeriodUsage->period);
        self::assertSame(5, $result->decidingPeriodUsage->leftValue);
    }

    public function testBetweenFullPeriodsThatResetTogetherTheLongerDecides(): void
    {
        // A Wednesday: the week and the month both reset on Monday 2027-02-01.
        $service = $this->createService();
        $now = new DateTimeImmutable('2027-01-27 10:00:00 UTC');
        $result = $service->createCheckResult(
            [
                $service->createCurrentPeriodUsage(new PeriodLimit(Period::Week, 1), 1, $now),
                $service->createCurrentPeriodUsage(new PeriodLimit(Period::Month, 1), 1, $now),
            ],
            1,
        );

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Month, $result->decidingPeriodUsage->period);
    }

    public function testAMonthThatResetsBeforeTheWeekDoesNotDecide(): void
    {
        // A Tuesday: the month resets on Thursday 2026-10-01, the week on Monday 2026-10-05.
        $service = $this->createService();
        $now = new DateTimeImmutable('2026-09-29 10:00:00 UTC');
        $result = $service->createCheckResult(
            [
                $service->createCurrentPeriodUsage(new PeriodLimit(Period::Week, 1), 1, $now),
                $service->createCurrentPeriodUsage(new PeriodLimit(Period::Month, 1), 1, $now),
            ],
            1,
        );

        self::assertFalse($result->isAllowed);
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
        self::assertSame('2026-10-05 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
    }

    public function testMoreUsedThanTheMaximumLeavesNothing(): void
    {
        // A maximum lowered after use.
        $result = $this->createService()->createCheckResult([$this->createUsage(Period::Day, 2, 5)], 1);

        self::assertFalse($result->isAllowed);
        self::assertSame(5, $result->decidingPeriodUsage->usedValue);
        self::assertSame(0, $result->decidingPeriodUsage->leftValue);
    }

    public function testAConsumeResultCountsTheAmountWhenItFits(): void
    {
        $result = $this->createService()->createConsumeResult(
            [$this->createUsage(Period::Day, 5, 1), $this->createUsage(Period::Week, 20, 1)],
            2,
        );

        self::assertTrue($result->isAllowed);
        self::assertSame([3, 3], array_column($result->periodUsages, 'usedValue'));
        self::assertSame([2, 17], array_column($result->periodUsages, 'leftValue'));
        self::assertSame(Period::Day, $result->decidingPeriodUsage->period);
        self::assertSame('2026-11-19 00:00:00', $result->decidingPeriodUsage->resetsAt->format('Y-m-d H:i:s'));
    }

    public function testAConsumeResultCountsNothingWhenTheAmountDoesNotFit(): void
    {
        $result = $this->createService()->createConsumeResult(
            [$this->createUsage(Period::Day, 5, 4), $this->createUsage(Period::Week, 20, 4)],
            2,
        );

        self::assertFalse($result->isAllowed);
        self::assertSame([4, 4], array_column($result->periodUsages, 'usedValue'));
        self::assertSame([1, 16], array_column($result->periodUsages, 'leftValue'));
        self::assertSame(Period::Day, $result->decidingPeriodUsage->period);
    }

    private function createService(): UsageResultService
    {
        return new UsageResultService(new PeriodWindowService());
    }

    private function createUsage(Period $period, int $maxValue, int $usedValue): PeriodUsage
    {
        return $this->createService()->createCurrentPeriodUsage(
            new PeriodLimit($period, $maxValue),
            $usedValue,
            new DateTimeImmutable(self::NOW),
        );
    }
}
