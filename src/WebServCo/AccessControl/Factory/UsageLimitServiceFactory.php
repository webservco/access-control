<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Factory;

use Override;
use WebServCo\AccessControl\Contract\UsageLimitServiceFactoryInterface;
use WebServCo\AccessControl\Contract\UsageLimitServiceInterface;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\AccessControl\Service\SystemClock;
use WebServCo\AccessControl\Service\UsageCounterService;
use WebServCo\AccessControl\Service\UsageLimitService;
use WebServCo\AccessControl\Service\UsageResultService;
use WebServCo\Data\Contract\Extraction\DataExtractionContainerInterface;
use WebServCo\Database\Contract\PDOContainerInterface;
use WebServCo\Database\Service\TransactionalDatabaseService;

/**
 * The tables are `access_level`, `access_level_limit`, `access_user_level`, `access_user_limit` and
 * `access_usage_counter` (`resources/access-control.sql`).
 */
final readonly class UsageLimitServiceFactory implements UsageLimitServiceFactoryInterface
{
    public function __construct(
        private DataExtractionContainerInterface $dataExtractionContainer,
        private PDOContainerInterface $pdoContainer,
    ) {
    }

    #[Override]
    public function createUsageLimitService(?string $databaseName = null): UsageLimitServiceInterface
    {
        $periodWindowService = new PeriodWindowService();

        return new UsageLimitService(
            new SystemClock(),
            new InputValidator(),
            new PDOLimitStorage($this->dataExtractionContainer, $this->pdoContainer, $databaseName),
            new UsageCounterService(
                $periodWindowService,
                new TransactionalDatabaseService($this->pdoContainer),
                new PDOUsageCounterStorage($this->dataExtractionContainer, $this->pdoContainer, $databaseName),
                new UsageResultService($periodWindowService),
            ),
        );
    }
}
