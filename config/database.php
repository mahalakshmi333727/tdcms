<?php
/**
 * Database Connection (PDO)
 * Uses a singleton pattern so only one connection is created per request.
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    // ------------------------------------------------------------
    // XAMPP defaults out of the box: host 127.0.0.1, port 3306,
    // user 'root', empty password. These work immediately after you
    // import sql/schema.sql via phpMyAdmin or the mysql CLI bundled
    // with XAMPP. Production must provide DB_HOST, DB_PORT, DB_NAME,
    // DB_USER, and DB_PASS through the process environment.
    // ------------------------------------------------------------
    private const DB_CHARSET = 'utf8mb4';

    private function __construct()
    {
        // Prevent direct instantiation
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $environment = APP_ENV;
            $defaults = $environment === 'development'
                ? [
                    'DB_HOST' => '127.0.0.1',
                    'DB_PORT' => '3306',
                    'DB_NAME' => 'tdcms',
                    'DB_USER' => 'root',
                    'DB_PASS' => '',
                ]
                : [];
            $configuration = [];
            foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
                $value = getenv($name);
                $configuration[$name] = $value !== false
                    ? $value
                    : ($defaults[$name] ?? '');
            }

            if (
                $configuration['DB_HOST'] === ''
                || $configuration['DB_PORT'] === ''
                || $configuration['DB_NAME'] === ''
                || $configuration['DB_USER'] === ''
                || ($environment !== 'development' && $configuration['DB_PASS'] === '')
            ) {
                error_log('Database configuration is incomplete for the application environment.');
                if (PHP_SAPI === 'cli') {
                    throw new RuntimeException('Database configuration is incomplete.');
                }
                http_response_code(500);
                die('A system error occurred. Please try again later.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $configuration['DB_HOST'],
                $configuration['DB_PORT'],
                $configuration['DB_NAME'],
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    $configuration['DB_USER'],
                    $configuration['DB_PASS'],
                    $options
                );
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                if (PHP_SAPI === 'cli') {
                    throw new RuntimeException('Database connection failed.', 0, $e);
                }
                http_response_code(500);
                die('A system error occurred. Please try again later.');
            }
        }

        return self::$instance;
    }

    // Prevent cloning and unserialization of the singleton
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
