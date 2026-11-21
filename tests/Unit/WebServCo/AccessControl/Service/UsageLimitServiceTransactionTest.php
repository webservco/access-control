<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\UsageLimitFixture;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\AccessControl\Service\UsageCounterService;
use WebServCo\AccessControl\Service\UsageLimitService;
use WebServCo\AccessControl\Service\UsageResultService;

/**
 * The transaction of `consume()`: the caller's, and errors in it.
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
final class UsageLimitServiceTransactionTest extends TestCase
{
    public function testConsumeRefusesToRunInsideTheCallersTransaction(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3]);
        $pdo = $fixture->getPDOContainer()->getPDO();
        $pdo->beginTransaction();

        try {
            $fixture->getService()->consume('1', 'sync');
            self::fail('consume() ran inside a transaction.');
        } catch (LogicException $exception) {
            self::assertSame('consume() runs its own transaction: call it outside of one.', $exception->getMessage());
        }

        // The caller's transaction goes on.
        self::assertTrue($pdo->inTransaction());
        self::assertSame([], $fixture->getPDOContainer()->fetchRows('access_usage_counter'));
    }

    public function testAnErrorWhileCountingRollsBackAndIsThrown(): void
    {
        $fixture = new UsageLimitFixture();
        $fixture->setDefaultLimit(['day' => 3, 'week' => 5]);
        $fixture->getPDOContainer()->getPDO()->exec(
            "CREATE TRIGGER fail_week BEFORE UPDATE ON access_usage_counter WHEN NEW.period = 'week'"
            . " BEGIN SELECT RAISE(ABORT, 'week failed'); END",
        );

        try {
            $fixture->getService()->consume('1', 'sync');
            self::fail('The error was not thrown.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('week failed', $exception->getMessage());
        }

        self::assertFalse($fixture->getPDOContainer()->getPDO()->inTransaction());
        // The counters were created before the transaction; the day's unit was rolled back.
        self::assertSame([0, 0], $fixture->fetchUsedValues());
    }

    public function testATransactionTheDatabaseEndedIsNotRolledBackAgain(): void
    {
        $fixture = new UsageLimitFixture();
        // As InnoDB does on a deadlock.
        $fixture->setDefaultLimit(['day' => 3, 'week' => 5]);
        $fixture->getPDOContainer()->getPDO()->exec(
            "CREATE TRIGGER end_week BEFORE UPDATE ON access_usage_counter WHEN NEW.period = 'week'"
            . " BEGIN SELECT RAISE(ROLLBACK, 'week rolled back'); END",
        );

        try {
            $fixture->getService()->consume('1', 'sync');
            self::fail('The error was not thrown.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('week rolled back', $exception->getMessage());
        }

        self::assertFalse($fixture->getPDOContainer()->getPDO()->inTransaction());
        self::assertSame([0, 0], $fixture->fetchUsedValues());
    }
}
