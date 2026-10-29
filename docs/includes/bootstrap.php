<?php
/**
 * Application Bootstrap
 * Loads configuration, database, helpers, and models, and starts a secure
 * session. Every entry point (index.php, ajax/*.php) includes this first.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/ActivityLog.php';
require_once __DIR__ . '/../models/LoginLog.php';
require_once __DIR__ . '/../helpers/validators.php';
require_once __DIR__ . '/../helpers/xlsx_reader.php';
require_once __DIR__ . '/../helpers/task_import.php';

// Security headers (defense in depth, complements .htaccess)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; script-src 'self' cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' cdn.jsdelivr.net;");

secure_session_start();

$pdo = Database::getConnection();
