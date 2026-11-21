<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\SqlitePDOContainer;
use WebServCo\AccessControl\Factory\AccessServiceFactory;
use WebServCo\AccessControl\Factory\UsageLimitServiceFactory;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\AccessService;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\AccessControl\Service\Storage\PDOUserGrantStorage;
use WebServCo\AccessControl\Service\SystemClock;
use WebServCo\AccessControl\Service\UsageCounterService;
use WebServCo\AccessControl\Service\UsageLimitService;
use WebServCo\AccessControl\Service\UsageResultService;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

/**
 * The factories wired the way an application wires them, with the system clock.
 */
#[CoversClass(AccessServiceFactory::class)]
#[CoversClass(UsageLimitServiceFactory::class)]
#[UsesClass(AbstractPDOStorage::class)]
#[UsesClass(AccessService::class)]
#[UsesClass(InputValidator::class)]
#[UsesClass(PDOLimitStorage::class)]
#[UsesClass(PDOUsageCounterStorage::class)]
#[UsesClass(PDOUserGrantStorage::class)]
#[UsesClass(PeriodLimit::class)]
#[UsesClass(PeriodUsage::class)]
#[UsesClass(PeriodWindowService::class)]
#[UsesClass(SystemClock::class)]
#[UsesClass(UsageCounterKey::class)]
#[UsesClass(UsageCounterService::class)]
#[UsesClass(UsageLimitService::class)]
#[UsesClass(UsageResult::class)]
#[UsesClass(UsageResultService::class)]
final class FactoriesTest extends TestCase
{
    public function testTheAccessServiceWorks(): void
    {
        $service = $this->createAccessServiceFactory(new SqlitePDOContainer())->createAccessService();

        self::assertTrue($service->grantRole('1', 'admin'));
        self::assertTrue($service->grantFeature('1', 'v3_access'));
        self::assertTrue($service->hasRole('1', 'admin'));
        self::assertSame(['1'], $service->fetchUserIdsWithFeature('v3_access'));
    }

    public function testTheAccessServiceUsesTheNamedDatabase(): void
    {
        $pdoContainer = new SqlitePDOContainer('oc_v3_ogger');
        $service = $this->createAccessServiceFactory($pdoContainer)->createAccessService('oc_v3_ogger');
        $service->grantRole('1', 'admin');
        $service->grantFeature('1', 'v3_access');

        self::assertCount(1, $pdoContainer->fetchRows('access_user_role'));
        self::assertCount(1, $pdoContainer->fetchRows('access_user_feature'));
        self::assertSame([], $pdoContainer->fetchMainRows('access_user_role'));
        self::assertSame([], $pdoContainer->fetchMainRows('access_user_feature'));
    }

    public function testTheUsageLimitServiceWorks(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $pdoContainer->insertLevelLimit('default', 'sync', 'week', 5);
        $result = $this->createUsageLimitServiceFactory($pdoContainer)->createUsageLimitService()->consume('1', 'sync');

        self::assertTrue($result->isAllowed);
        self::assertSame(Period::Week, $result->decidingPeriodUsage->period);
        self::assertSame(4, $result->decidingPeriodUsage->leftValue);
    }

    public function testTheUsageLimitServiceUsesTheNamedDatabase(): void
    {
        $pdoContainer = new SqlitePDOContainer('oc_v3_ogger');
        $pdoContainer->insertLevel('pro');
        $pdoContainer->insertLevelLimit('pro', 'sync', 'week', 50);
        $service = $this->createUsageLimitServiceFactory($pdoContainer)->createUsageLimitService('oc_v3_ogger');
        $service->setUserLevel('1', 'pro');
        $service->setUserLimit('1', 'sync', Period::Day, 10);

        self::assertTrue($service->consume('1', 'sync')->isAllowed);
        self::assertCount(2, $pdoContainer->fetchRows('access_usage_counter'));

        foreach (['access_user_level', 'access_user_limit', 'access_usage_counter'] as $tableName) {
            self::assertSame([], $pdoContainer->fetchMainRows($tableName));
        }
    }

    private function createAccessServiceFactory(SqlitePDOContainer $pdoContainer): AccessServiceFactory
    {
        return new AccessServiceFactory(
            (new DataExtractionContainerFactory())->createDataExtractionContainer(false),
            $pdoContainer,
        );
    }

    private function createUsageLimitServiceFactory(SqlitePDOContainer $pdoContainer): UsageLimitServiceFactory
    {
        return new UsageLimitServiceFactory(
            (new DataExtractionContainerFactory())->createDataExtractionContainer(false),
            $pdoContainer,
        );
    }
}
