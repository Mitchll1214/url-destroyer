<?php
/**
 * Database Layer — SQLite via PDO
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dataDir = dirname(DB_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        initSchema($pdo);
    }
    return $pdo;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
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
        CREATE TABLE IF NOT EXISTS access_logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id     INTEGER NOT NULL,
            ip          TEXT    DEFAULT '',
            user_agent  TEXT    DEFAULT '',
            referer     TEXT    DEFAULT '',
            form_data   TEXT    DEFAULT '',
            accessed_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ");

    // Login rate limiting
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ip          TEXT    NOT NULL,
            attempted_at TEXT   NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Form drafts — save partial form progress
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS form_drafts (
            token       TEXT PRIMARY KEY,
            form_data   TEXT NOT NULL DEFAULT '{}',
            updated_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    // Insert default settings if not present
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute(['default_access_timeout', DEFAULT_ACCESS_TIMEOUT]);
    $stmt->execute(['default_absolute_expiry_hours', DEFAULT_ABSOLUTE_EXPIRY_HOURS]);

    // Schema migration: expire_on_submit flag
    try {
        $pdo->exec("ALTER TABLE links ADD COLUMN expire_on_submit INTEGER NOT NULL DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists
    }
}
