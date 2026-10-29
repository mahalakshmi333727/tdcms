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
    // with XAMPP. For a shared/production server, create a dedicated
    // least-privilege MySQL user instead (see docs/INSTALLATION_GUIDE.md)
    // and update these constants, or better, load them from environment
    // variables.
    // ------------------------------------------------------------
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'tdcms';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    private function __construct()
    {
        // Prevent direct instantiation
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
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
