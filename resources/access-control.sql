-- webservco/access-control tables for MariaDB and MySQL. Times are UTC.
-- Binary collation: user ids and keys compare exactly, as in the SQLite tests. A user id can't end with a space,
-- which these collations would ignore.
-- No table references the application's user table, so a role or a feature can be granted before a user's first login.

CREATE TABLE access_user_role (
    user_id VARCHAR(64) NOT NULL,
    role_key VARCHAR(64) NOT NULL,
    when_added DATETIME NOT NULL,
    PRIMARY KEY (user_id, role_key),
    KEY role_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE access_user_feature (
    user_id VARCHAR(64) NOT NULL,
    feature_key VARCHAR(64) NOT NULL,
    when_added DATETIME NOT NULL,
    PRIMARY KEY (user_id, feature_key),
    KEY feature_key (feature_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- A user without a row in access_user_level is on `default`.
CREATE TABLE access_level (
    level_key VARCHAR(64) NOT NULL,
    PRIMARY KEY (level_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT INTO access_level (level_key) VALUES ('default');

CREATE TABLE access_user_level (
    user_id VARCHAR(64) NOT NULL,
    level_key VARCHAR(64) NOT NULL,
    PRIMARY KEY (user_id),
    KEY level_key (level_key),
    CONSTRAINT access_user_level_level FOREIGN KEY (level_key) REFERENCES access_level (level_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- The application's limits: one row per level, limit key and period. A max_value of 0 blocks.
CREATE TABLE access_level_limit (
    level_key VARCHAR(64) NOT NULL,
    limit_key VARCHAR(64) NOT NULL,
    period VARCHAR(16) NOT NULL,
    max_value INT UNSIGNED NOT NULL,
    PRIMARY KEY (level_key, limit_key, period),
    CONSTRAINT access_level_limit_level FOREIGN KEY (level_key) REFERENCES access_level (level_key),
    CONSTRAINT access_level_limit_period CHECK (period IN ('day', 'week', 'month'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- For one user, a row replaces the level's value for that period, or adds a period.
CREATE TABLE access_user_limit (
    user_id VARCHAR(64) NOT NULL,
    limit_key VARCHAR(64) NOT NULL,
    period VARCHAR(16) NOT NULL,
    max_value INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, limit_key, period),
    CONSTRAINT access_user_limit_period CHECK (period IN ('day', 'week', 'month'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- One row per user, limit key, period and window, kept until deleteCountersBefore() removes it.
-- used_value is BIGINT: an amount is added before it is compared with max_value, and rolled back when it doesn't fit.
CREATE TABLE access_usage_counter (
    user_id VARCHAR(64) NOT NULL,
    limit_key VARCHAR(64) NOT NULL,
    period VARCHAR(16) NOT NULL,
    window_start DATETIME NOT NULL,
    used_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, limit_key, period, window_start),
    KEY period_window_start (period, window_start),
    CONSTRAINT access_usage_counter_period CHECK (period IN ('day', 'week', 'month'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
