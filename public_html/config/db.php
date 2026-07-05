<?php
/**
 * ======================================================
 * DATABASE CONFIGURATION & PDO CONNECTION WRAPPER
 * Ludo Tournament Platform - Production Ready
 * Version: 1.0.0
 * ======================================================
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// ======================================================
// DATABASE CONSTANTS
// ======================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ludo_tournament_db');
define('DB_USER', 'ludo_user');
define('DB_PASS', 'Secure@Ludo2026#!');
define('DB_CHARSET', 'utf8mb4');

// ======================================================
// APPLICATION CONSTANTS
// ======================================================
define('PLATFORM_FEE', 0.15); // 15% platform fee
define('BASE_URL', 'https://yourdomain.com'); // Change to actual domain
define('SITE_NAME', 'Ludo Tournament Pro');
define('ADMIN_EMAIL', 'support@yourdomain.com');
define('TIMEZONE', 'Asia/Kolkata');
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('CSRF_TOKEN_LENGTH', 32);

// ======================================================
// SET TIMEZONE
// ======================================================
date_default_timezone_set(TIMEZONE);

// ======================================================
// DATABASE CLASS - PDO WRAPPER
// ======================================================
class Database {
    /**
     * @var PDO|null
     */
    private static $instance = null;
    
    /**
     * @var PDO
     */
    private $connection;
    
    /**
     * @var array
     */
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $this->options);
        } catch (PDOException $e) {
            $this->handleError("Connection failed", $e);
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing
     */
    public function __wakeup() {}
    
    /**
     * Get singleton instance
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    /**
     * Execute prepared statement with parameters
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function execute(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new PDOException("Failed to prepare statement: " . $this->connection->errorInfo()[2]);
            }
            
            // Bind parameters with type
            foreach ($params as $key => $value) {
                $paramType = $this->getParamType($value);
                $stmt->bindValue($key, $value, $paramType);
            }
            
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            $this->handleError("Query execution failed", $e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Fetch all rows
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch single row
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Fetch single column value
     * @param string $sql
     * @param array $params
     * @param int $column
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * Insert record and return last insert ID
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert(string $table, array $data): int {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES ({$placeholders})";
        
        $this->execute($sql, $data);
        return (int)$this->connection->lastInsertId();
    }
    
    /**
     * Update record(s)
     * @param string $table
     * @param array $data
     * @param array $where
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $where): int {
        $setParts = [];
        foreach ($data as $field => $value) {
            $setParts[] = "`{$field}` = :set_{$field}";
        }
        
        $whereParts = [];
        foreach ($where as $field => $value) {
            $whereParts[] = "`{$field}` = :where_{$field}";
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . 
               " WHERE " . implode(' AND ', $whereParts);
        
        // Merge data and where parameters with prefixes
        $params = [];
        foreach ($data as $field => $value) {
            $params["set_{$field}"] = $value;
        }
        foreach ($where as $field => $value) {
            $params["where_{$field}"] = $value;
        }
        
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete record(s)
     * @param string $table
     * @param array $where
     * @return int Number of affected rows
     */
    public function delete(string $table, array $where): int {
        $whereParts = [];
        foreach ($where as $field => $value) {
            $whereParts[] = "`{$field}` = :{$field}";
        }
        
        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->execute($sql, $where);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     * @return bool
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     * @return bool
     */
    public function commit(): bool {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     * @return bool
     */
    public function rollback(): bool {
        return $this->connection->rollBack();
    }
    
    /**
     * Check if in transaction
     * @return bool
     */
    public function inTransaction(): bool {
        return $this->connection->inTransaction();
    }
    
    /**
     * Get last inserted ID
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string {
        return $this->connection->lastInsertId($name);
    }
    
    /**
     * Quote string for SQL (use sparingly, prefer prepared statements)
     * @param string $string
     * @return string
     */
    public function quote(string $string): string {
        return $this->connection->quote($string);
    }
    
    /**
     * Determine PDO parameter type
     * @param mixed $value
     * @return int
     */
    private function getParamType($value): int {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }
    
    /**
     * Error handler with logging
     * @param string $message
     * @param PDOException $e
     * @param string|null $sql
     * @param array|null $params
     * @return void
     */
    private function handleError(string $message, PDOException $e, ?string $sql = null, ?array $params = null): void {
        $errorLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'sql' => $sql,
            'params' => $params,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        
        $logEntry = json_encode($errorLog, JSON_PRETTY_PRINT) . PHP_EOL;
        $logFile = dirname(__DIR__) . '/logs/db_errors.log';
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // In production, show generic error message
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            throw new PDOException("Database error occurred. Please try again later.");
        } else {
            throw $e;
        }
    }
}

// ======================================================
// SESSION MANAGEMENT WITH SECURE COOKIE FLAGS
// ======================================================
class SessionManager {
    /**
     * Initialize secure session
     * @return bool
     */
    public static function init(): bool {
        // Set session cookie parameters BEFORE session_start()
        $cookieParams = session_get_cookie_params();
        
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path' => '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        
        // Set session name for security
        session_name('LUDO_SESS_ID');
        
        // Attempt to start session
        if (session_status() === PHP_SESSION_NONE) {
            $result = session_start();
            
            // Regenerate session ID periodically to prevent fixation
            if (!isset($_SESSION['session_init_time'])) {
                $_SESSION['session_init_time'] = time();
                session_regenerate_id(true);
            }
            
            return $result;
        }
        
        return true;
    }
    
    /**
     * Set session variable safely
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session variable
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session variable exists
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session variable
     * @param string $key
     * @return void
     */
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session completely
     * @return bool
     */
    public static function destroy(): bool {
        // Clear all session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy session
        return session_destroy();
    }
    
    /**
     * Check if session is expired
     * @return bool
     */
    public static function isExpired(): bool {
        if (!isset($_SESSION['session_init_time'])) {
            return true;
        }
        
        return (time() - $_SESSION['session_init_time']) > SESSION_TIMEOUT;
    }
    
    /**
     * Refresh session expiry
     * @return void
     */
    public static function refresh(): void {
        $_SESSION['session_init_time'] = time();
    }
    
    /**
     * Regenerate session ID securely
     * @return bool
     */
    public static function regenerate(): bool {
        return session_regenerate_id(true);
    }
}

// ======================================================
// CSRF TOKEN GENERATION & VALIDATION
// ======================================================
class CSRFToken {
    /**
     * Generate CSRF token
     * @return string
     */
    public static function generate(): string {
        if (empty($_SESSION['csrf_token'])) {
            $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            $_SESSION['csrf_token'] = $token;
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     * @param string $token
     * @return bool
     */
    public static function validate(string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Token expires after 1 hour
        if (isset($_SESSION['csrf_token_time'])) {
            if ((time() - $_SESSION['csrf_token_time']) > 3600) {
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token HTML input field
     * @return string
     */
    public static function getHTMLField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

// ======================================================
// UTILITY FUNCTIONS
// ======================================================

/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random string
 * @param int $length
 * @return string
 */
function generateRandomString(int $length = 10): string {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Generate unique room code for matches
 * @return string
 */
function generateRoomCode(): string {
    return generateRandomString(6);
}

/**
 * Generate unique referral code
 * @return string
 */
function generateReferralCode(): string {
    return 'REF' . generateRandomString(8);
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

/**
 * Calculate platform fee
 * @param float $entryFee
 * @return float
 */
function calculatePlatformFee(float $entryFee): float {
    return round($entryFee * PLATFORM_FEE, 2);
}

/**
 * Calculate prize pool
 * @param float $entryFee
 * @param int $players
 * @return float
 */
function calculatePrizePool(float $entryFee, int $players): float {
    $total = $entryFee * $players;
    $fee = calculatePlatformFee($total);
    return round($total - $fee, 2);
}

/**
 * JSON response helper
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $statusCode
 * @return void
 */
function jsonResponse(bool $success, string $message, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return SessionManager::has('user_id') && !SessionManager::isExpired();
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return SessionManager::get('user_id');
}

// ======================================================
// INITIALIZE SESSION ON INCLUDE
// ======================================================
SessionManager::init();

// ======================================================
// DATABASE CONNECTION READY
// ======================================================
// Usage: $db = Database::getInstance();
// Usage: $conn = $db->getConnection();

// ======================================================
// END OF CONFIGURATION FILE
// ======================================================
