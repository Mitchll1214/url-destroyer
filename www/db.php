<?php
/**
 * Database Layer — SQLite or MySQL via PDO
 */

require_once __DIR__ . '/config.php';

// ─── Table names ───
define('T_LINKS',          DB_TABLE_PREFIX . 'links');
define('T_ACCESS_LOGS',    DB_TABLE_PREFIX . 'access_logs');
define('T_SETTINGS',       DB_TABLE_PREFIX . 'settings');
define('T_LOGIN_ATTEMPTS', DB_TABLE_PREFIX . 'login_attempts');
define('T_FORM_DRAFTS',    DB_TABLE_PREFIX . 'form_drafts');

// ─── Helper functions for driver-specific SQL expressions ───

function db_now(): string {
    return DB_DRIVER === 'mysql' ? 'NOW()' : "datetime('now', 'localtime')";
}

function db_now_minus(int $minutes): string {
    if (DB_DRIVER === 'mysql') {
        return "NOW() - INTERVAL {$minutes} MINUTE";
    }
    return "datetime('now', 'localtime', '-{$minutes} minutes')";
}

function db_now_plus_seconds(int $seconds): string {
    if (DB_DRIVER === 'mysql') {
        return "NOW() + INTERVAL {$seconds} SECOND";
    }
    return "datetime('now', 'localtime', '+{$seconds} seconds')";
}

function db_dsn(): string {
    if (DB_DRIVER === 'mysql') {
        $charset = env('DB_CHARSET', 'utf8mb4');
        return "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset={$charset}";
    }
    return 'sqlite:' . DB_PATH;
}

// ─── Core PDO connection (used by DB class and schema init) ───

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_DRIVER === 'mysql') {
            $pdo = new PDO(db_dsn(), DB_USERNAME, DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET NAMES utf8mb4");
        } else {
            $dataDir = dirname(DB_PATH);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
        initSchema($pdo);
    }
    return $pdo;
}

// ─── DB wrapper class — auto-translates table names + MySQL syntax ───

class DB {
    private static ?PDO $pdo = null;

    private static function pdo(): PDO {
        if (self::$pdo === null) {
            self::$pdo = getDB();
        }
        return self::$pdo;
    }

    public static function prepare(string $sql): PDOStatement {
        return self::pdo()->prepare(self::translate($sql));
    }

    public static function exec(string $sql): int {
        return self::pdo()->exec(self::translate($sql));
    }

    public static function query(string $sql): PDOStatement {
        return self::pdo()->query(self::translate($sql));
    }

    /**
     * Translate SQL for the active driver:
     * 1. Replace table names with prefixed versions
     * 2. Translate SQLite-specific syntax for MySQL
     */
    private static function translate(string $sql): string {
        // Step 1: Table prefix substitution
        $prefix = DB_TABLE_PREFIX;
        if ($prefix !== '') {
            $tables = ['links', 'access_logs', 'settings', 'login_attempts', 'form_drafts'];
            foreach ($tables as $table) {
                $sql = preg_replace(
                    '/\b(FROM|JOIN|INTO|TABLE|UPDATE|REFERENCES|DELETE\s+FROM)\s+' . preg_quote($table, '/') . '\b/i',
                    '$1 ' . $prefix . $table,
                    $sql
                );
            }
        }

        // Step 2: MySQL-specific syntax translation
        if (DB_DRIVER === 'mysql') {
            $sql = str_replace(
                [
                    "datetime('now', 'localtime')",
                    "datetime('now','localtime')",
                    "INSERT OR IGNORE",
                    "INSERT OR REPLACE",
                ],
                [
                    "NOW()",
                    "NOW()",
                    "INSERT IGNORE",
                    "REPLACE",
                ],
                $sql
            );
        }

        return $sql;
    }
}

// ─── Schema initialization ───

function initSchema(PDO $pdo): void {
    if (DB_DRIVER === 'mysql') {
        initSchemaMySQL($pdo);
    } else {
        initSchemaSQLite($pdo);
    }
}

function initSchemaSQLite(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_LINKS . " (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            token       TEXT    NOT NULL UNIQUE,
            campaign_name TEXT  NOT NULL DEFAULT '',
            target_content TEXT NOT NULL DEFAULT '',
            access_timeout    INTEGER NOT NULL DEFAULT 86400,
            absolute_expiry_hours INTEGER NOT NULL DEFAULT 24,
            max_accesses INTEGER NOT NULL DEFAULT 1,
            access_count INTEGER NOT NULL DEFAULT 0,
            status      TEXT    NOT NULL DEFAULT 'active',
            created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
            first_accessed_at TEXT DEFAULT NULL,
            expires_at  TEXT    DEFAULT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_ACCESS_LOGS . " (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id     INTEGER NOT NULL,
            ip          TEXT    DEFAULT '',
            user_agent  TEXT    DEFAULT '',
            referer     TEXT    DEFAULT '',
            form_data   TEXT    DEFAULT '',
            accessed_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (link_id) REFERENCES " . T_LINKS . "(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_SETTINGS . " (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_LOGIN_ATTEMPTS . " (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ip          TEXT    NOT NULL,
            attempted_at TEXT   NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_FORM_DRAFTS . " (
            token       TEXT PRIMARY KEY,
            form_data   TEXT NOT NULL DEFAULT '{}',
            updated_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Default settings
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO " . T_SETTINGS . " (key, value) VALUES (?, ?)");
    $stmt->execute(['default_access_timeout', DEFAULT_ACCESS_TIMEOUT]);
    $stmt->execute(['default_absolute_expiry_hours', DEFAULT_ABSOLUTE_EXPIRY_HOURS]);

    // Migration: expire_on_submit
    try {
        $pdo->exec("ALTER TABLE " . T_LINKS . " ADD COLUMN expire_on_submit INTEGER NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists
    }
}

function initSchemaMySQL(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_LINKS . " (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token       VARCHAR(64) NOT NULL UNIQUE,
            campaign_name VARCHAR(255) NOT NULL DEFAULT '',
            target_content TEXT NOT NULL DEFAULT '',
            access_timeout    INT UNSIGNED NOT NULL DEFAULT 86400,
            absolute_expiry_hours INT UNSIGNED NOT NULL DEFAULT 24,
            max_accesses INT UNSIGNED NOT NULL DEFAULT 1,
            access_count INT UNSIGNED NOT NULL DEFAULT 0,
            status      VARCHAR(16) NOT NULL DEFAULT 'active',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            first_accessed_at DATETIME DEFAULT NULL,
            expires_at  DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_ACCESS_LOGS . " (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            link_id     INT UNSIGNED NOT NULL,
            ip          VARCHAR(45) DEFAULT '',
            user_agent  TEXT DEFAULT '',
            referer     TEXT DEFAULT '',
            form_data   TEXT DEFAULT '',
            accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (link_id) REFERENCES " . T_LINKS . "(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_SETTINGS . " (
            `key`   VARCHAR(64) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_LOGIN_ATTEMPTS . " (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip          VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS " . T_FORM_DRAFTS . " (
            token       VARCHAR(64) PRIMARY KEY,
            form_data   TEXT NOT NULL DEFAULT ('{}'),
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Default settings
    $stmt = $pdo->prepare("INSERT IGNORE INTO " . T_SETTINGS . " (`key`, `value`) VALUES (?, ?)");
    $stmt->execute(['default_access_timeout', DEFAULT_ACCESS_TIMEOUT]);
    $stmt->execute(['default_absolute_expiry_hours', DEFAULT_ABSOLUTE_EXPIRY_HOURS]);

    // Migration: expire_on_submit
    try {
        $pdo->exec("ALTER TABLE " . T_LINKS . " ADD COLUMN expire_on_submit TINYINT UNSIGNED NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists
    }
}
