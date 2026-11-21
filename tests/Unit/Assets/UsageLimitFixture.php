<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\PeriodWindowService;
use WebServCo\AccessControl\Service\Storage\PDOLimitStorage;
use WebServCo\AccessControl\Service\Storage\PDOUsageCounterStorage;
use WebServCo\AccessControl\Service\UsageCounterService;
use WebServCo\AccessControl\Service\UsageLimitService;
use WebServCo\AccessControl\Service\UsageResultService;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;
use WebServCo\Database\Service\TransactionalDatabaseService;

use function sort;

/**
 * `UsageLimitService` over SQLite in memory, wired as `UsageLimitServiceFactory` wires it, with a fixed clock that
 * starts on Wednesday 2026-11-18 at 10:00 UTC.
 */
final readonly class UsageLimitFixture
{
    private FixedClock $clock;

    private SqlitePDOContainer $pdoContainer;

    private UsageLimitService $service;

    public function __construct()
    {
        $this->clock = new FixedClock('2026-11-18 10:00:00 UTC');
        $this->pdoContainer = new SqlitePDOContainer();
        $dataExtractionContainer = (new DataExtractionContainerFactory())->createDataExtractionContainer(false);
        $periodWindowService = new PeriodWindowService();
        $this->service = new UsageLimitService(
            $this->clock,
            new InputValidator(),
            new PDOLimitStorage($dataExtractionContainer, $this->pdoContainer),
            new UsageCounterService(
                $periodWindowService,
                new TransactionalDatabaseService($this->pdoContainer),
                new PDOUsageCounterStorage($dataExtractionContainer, $this->pdoContainer),
                new UsageResultService($periodWindowService),
            ),
        );
    }

    /**
     * @return list<array{string,string,int}> period, window start and used value, sorted
     */
    public function fetchCounters(): array
    {
        $extraction = (new DataExtractionContainerFactory())
            ->createDataExtractionContainer(false)
            ->getStrictArrayDataExtractionService();
        $counters = [];

        foreach ($this->pdoContainer->fetchRows('access_usage_counter') as $row) {
            $counters[] = [
                $extraction->getString($row, 'period'),
                $extraction->getString($row, 'window_start'),
                $extraction->getInt($row, 'used_value'),
            ];
        }

        sort($counters);

        return $counters;
    }

    /**
     * @return list<int> the used value of each counter, in the order they were created
     */
    public function fetchUsedValues(): array
    {
        $extraction = (new DataExtractionContainerFactory())
            ->createDataExtractionContainer(false)
            ->getStrictArrayDataExtractionService();
        $usedValues = [];

        foreach ($this->pdoContainer->fetchRows('access_usage_counter') as $row) {
            $usedValues[] = $extraction->getInt($row, 'used_value');
        }

        return $usedValues;
    }

    public function getClock(): FixedClock
    {
        return $this->clock;
    }

    public function getPDOContainer(): SqlitePDOContainer
    {
        return $this->pdoContainer;
    }

    public function getService(): UsageLimitService
    {
        return $this->service;
    }

    /**
     * The limit key `sync` of the `default` level.
     *
     * @param array<string,int> $maxValues by period value
     */
    public function setDefaultLimit(array $maxValues): bool
    {
        foreach ($maxValues as $period => $maxValue) {
            $this->pdoContainer->insertLevelLimit('default', 'sync', $period, $maxValue);
        }

        return true;
    }
}
