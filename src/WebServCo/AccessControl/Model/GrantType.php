<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model;

/**
 * What a user is granted: a role or a feature flag. Each has its own table.
 */
enum GrantType
{
    case Feature;

    case Role;
}
