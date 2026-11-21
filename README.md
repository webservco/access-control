# webservco/access-control

A PHP component/library. Please see [webservco/sandbox](https://webservco.github.io/sandbox/) for an example project using these types of components.

Roles, feature flags and usage limits per user, with access levels, per-user overrides and counters in fixed UTC windows.

- Requires: the tables in [`resources/access-control.sql`](resources/access-control.sql), on MariaDB or MySQL (InnoDB)
- Related: `webservco/database` (the PDO container); `webservco/auth` (its account IDs fit as user IDs)

---

## Installation

```shell
composer require webservco/access-control
```

---

## Usage

The application gives the keys their meaning, usually with enums. A key (a role, a feature flag, an access level, a limit) is 1 to 64 lowercase letters, digits, `_`, `.` or `-`. A user ID is 1 to 64 characters, not ending with a space. Anything else throws an `InvalidArgumentException`. No table references the application's user table, so a role or a flag can be granted before the user's first login.

```php
use WebServCo\AccessControl\Factory\AccessServiceFactory;
use WebServCo\AccessControl\Factory\UsageLimitServiceFactory;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

$dataExtractionContainer = (new DataExtractionContainerFactory())->createDataExtractionContainer(false);
// $pdoContainer: \WebServCo\Database\Contract\PDOContainerInterface
$accessServiceFactory = new AccessServiceFactory($dataExtractionContainer, $pdoContainer);
$usageLimitServiceFactory = new UsageLimitServiceFactory($dataExtractionContainer, $pdoContainer);
// The database name is optional, for an application that names the database in its queries.
// \WebServCo\AccessControl\Contract\AccessServiceInterface
$accessService = $accessServiceFactory->createAccessService('example_db');
// \WebServCo\AccessControl\Contract\UsageLimitServiceInterface
$usageLimitService = $usageLimitServiceFactory->createUsageLimitService('example_db');

// Roles and feature flags. Grant and revoke say whether they changed anything.
$accessService->grantFeature($userId, 'beta');
$isAdmin = $accessService->hasRole($userId, 'admin');
$adminIds = $accessService->fetchUserIdsWithRole('admin');

// Counts one export, only when every period of the limit has room for it. check() answers without counting.
$result = $usageLimitService->consume($userId, 'export');
if (!$result->isAllowed) {
    // Explain it: period, maxValue, usedValue, leftValue, resetsAt (UTC). All periods: $result->periodUsages.
    $periodUsage = $result->decidingPeriodUsage;
}
```

### Levels and limits

| Table | Holds |
| --- | --- |
| `access_user_role`, `access_user_feature` | Each user's roles and feature flags, with when they were added. |
| `access_level` | The access levels. The file adds `default`. |
| `access_user_level` | Each user's level. A user without a row is on `default`. |
| `access_level_limit` | Each level's maximum per limit key and period. |
| `access_user_limit` | One user's maximum for a period: it replaces the level's, or adds a period. |
| `access_usage_counter` | One counter per user, limit key, period and window. |

The levels and their limits are the application's data, in its own SQL:

```sql
INSERT INTO access_level_limit (level_key, limit_key, period, max_value)
VALUES ('default', 'export', 'week', 5), ('default', 'export', 'month', 20);
```

```php
use WebServCo\AccessControl\Model\Period;

$usageLimitService->setUserLevel($userId, 'pro'); // a level that exists
$usageLimitService->setUserLimit($userId, 'export', Period::Day, 2); // 0 blocks, null removes the override
// When a user is deleted: the level, the overrides and the counters. AccessServiceInterface has its own.
$usageLimitService->deleteUser($userId);
// The counters stay until the application deletes them, for example from a monthly job.
$usageLimitService->deleteCountersBefore(new DateTimeImmutable('-1 year'));
```

---

## Contracts

| Interface | Implementation | Purpose |
| --- | --- | --- |
| `Contract\AccessServiceInterface` | `Service\AccessService` | Roles and feature flags: check, grant, revoke, list, delete a user's. |
| `Contract\UsageLimitServiceInterface` | `Service\UsageLimitService` | Usage: check and count; a user's level and overrides; old counters. |
| `Contract\AccessServiceFactoryInterface` | `Factory\AccessServiceFactory` | Creates `AccessServiceInterface`. |
| `Contract\UsageLimitServiceFactoryInterface` | `Factory\UsageLimitServiceFactory` | Creates `UsageLimitServiceInterface`. |

Namespaces are relative to `WebServCo\AccessControl\`.

---

## Notes

- **Periods** are fixed UTC windows: `day` from 00:00, `week` from Monday at 00:00 (ISO 8601), `month` from the 1st at 00:00. A window resets when the next one starts.
- **A user's limit** is each period of the limit key from the user's level, with the user's overrides on top. A maximum of 0 blocks. A limit key with no period for the user (none for the level, no override) throws an `OutOfBoundsException`, so a forgotten limit or a mistyped key shows at once instead of letting everything through.
- **The deciding period** explains the result. After a refusal, it is the full period that resets last, because the user can go on only then (near the end of a month, the week can reset after the month). Otherwise it is the period with the least left.
- **Counting.** `consume()` creates the missing counters with 0, then, in a transaction of its own, adds the amount to the counter of each period, in the order day, week, month, and reads each one back. When one is over its maximum, everything is rolled back. The first UPDATE locks the row until the end of the transaction, so two requests can't both take the last unit, and the fixed order makes two requests for the same user and key wait for each other instead of deadlocking. The counters are created before the transaction because in InnoDB a duplicate-key error inside one keeps a shared lock on the row, and two such transactions would deadlock on their updates. A database error rolls the transaction back and is thrown.
- **The tests** run on SQLite in memory, which takes none of these locks. Try the reasoning above on MariaDB or MySQL in the application.
- **Transactions.** `consume()` can't run inside one of the caller's (PDO has no nested transactions): it throws a `LogicException`. Every other method runs single statements, so it can be part of the caller's transaction, for example when an account is deleted.
- **Old counters** are kept, one row per window, so the counts can tune the limits. `deleteCountersBefore($date)` deletes, for each period, the windows before the one that holds `$date`. A running window is never deleted, even for a date in the future.
- **Collation.** The tables use `utf8mb4_bin`, so user IDs and keys compare exactly, as in SQLite. MySQL ignores trailing spaces when it compares, which is why a user ID can't end with one.

---

## Development

```shell
# Start project.
ddev start
# Install dependencies.
ddev c u
# Run all validations.
ddev c c
# Run unit tests, with coverage (needs Xdebug, which DDEV leaves off).
ddev xdebug on && ddev c test:dox; ddev xdebug off
```

---

## License

[MIT](LICENSE.md)
