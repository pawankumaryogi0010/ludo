<?php
/**
 * ======================================================
 * DATABASE CONFIGURATION & CORE SECURITY
 * Ludo Tournament Platform - Production Ready
 * Version: 4.0.0 - SECURE & COMPLETE
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
define('PLATFORM_FEE', 0.15);
define('BASE_URL', 'https://yourdomain.com');
define('SITE_NAME', 'Ludo Tournament Pro');
define('ADMIN_EMAIL', 'support@yourdomain.com');
define('TIMEZONE', 'Asia/Kolkata');
define('SESSION_TIMEOUT', 1800);
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
    private static $instance = null;
    private $connection;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $this->options);
        } catch (PDOException $e) {
            $this->handleError("Connection failed", $e);
        }
    }
    
    private function __clone() {}
    public function __wakeup() {}
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    public function execute(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new PDOException("Failed to prepare statement: " . $this->connection->errorInfo()[2]);
            }
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
    
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function fetchColumn(string $sql, array $params = [], int $column = 0) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    public function insert(string $table, array $data): int {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES ({$placeholders})";
        $this->execute($sql, $data);
        return (int)$this->connection->lastInsertId();
    }
    
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
    
    public function delete(string $table, array $where): int {
        $whereParts = [];
        foreach ($where as $field => $value) {
            $whereParts[] = "`{$field}` = :{$field}";
        }
        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->execute($sql, $where);
        return $stmt->rowCount();
    }
    
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->connection->commit();
    }
    
    public function rollback(): bool {
        return $this->connection->rollBack();
    }
    
    public function inTransaction(): bool {
        return $this->connection->inTransaction();
    }
    
    public function lastInsertId(?string $name = null): string {
        return $this->connection->lastInsertId($name);
    }
    
    public function quote(string $string): string {
        return $this->connection->quote($string);
    }
    
    private function getParamType($value): int {
        if (is_int($value)) return PDO::PARAM_INT;
        elseif (is_bool($value)) return PDO::PARAM_BOOL;
        elseif (is_null($value)) return PDO::PARAM_NULL;
        else return PDO::PARAM_STR;
    }
    
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
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
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
    public static function init(): bool {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path' => '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('LUDO_SESS_ID');
        
        if (session_status() === PHP_SESSION_NONE) {
            $result = session_start();
            if (!isset($_SESSION['session_init_time'])) {
                $_SESSION['session_init_time'] = time();
                session_regenerate_id(true);
            }
            return $result;
        }
        return true;
    }
    
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }
    
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }
    
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }
    
    public static function destroy(): bool {
        $_SESSION = [];
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
        return session_destroy();
    }
    
    public static function isExpired(): bool {
        if (!isset($_SESSION['session_init_time'])) {
            return true;
        }
        return (time() - $_SESSION['session_init_time']) > SESSION_TIMEOUT;
    }
    
    public static function refresh(): void {
        if (isset($_SESSION['session_init_time'])) {
            $_SESSION['session_init_time'] = time();
            // Regenerate session ID periodically for security
            if (rand(1, 10) === 1) { // 10% chance to regenerate
                session_regenerate_id(true);
            }
        }
    }
    
    public static function regenerate(): bool {
        return session_regenerate_id(true);
    }
    
    public static function validateAdminSession(): bool {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_token'])) {
            return false;
        }
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("
                SELECT id 
                FROM sessions 
                WHERE user_id = :admin_id 
                AND session_token = :token 
                AND is_active = 1 
                AND expires_at > NOW()
            ");
            $stmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':token' => $_SESSION['admin_token']
            ]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ======================================================
// CSRF TOKEN GENERATION & VALIDATION
// ======================================================
class CSRFToken {
    public static function generate(): string {
        if (empty($_SESSION['csrf_token'])) {
            $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
            $_SESSION['csrf_token'] = $token;
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validate(string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        if (isset($_SESSION['csrf_token_time'])) {
            if ((time() - $_SESSION['csrf_token_time']) > 3600) {
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getHTMLField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

// ======================================================
// UTILITY FUNCTIONS
// ======================================================

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateRandomString(int $length = 10): string {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generateRoomCode(): string {
    return generateRandomString(6);
}

function generateReferralCode(): string {
    return 'REF' . generateRandomString(8);
}

function formatCurrency(float $amount): string {
    return '₹' . number_format($amount, 2);
}

function calculatePlatformFee(float $entryFee): float {
    return round($entryFee * PLATFORM_FEE, 2);
}

function calculatePrizePool(float $entryFee, int $players): float {
    $total = $entryFee * $players;
    $fee = calculatePlatformFee($total);
    return round($total - $fee, 2);
}

function jsonResponse(bool $success, string $message, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

function isLoggedIn(): bool {
    return SessionManager::has('user_id') && !SessionManager::isExpired();
}

function getCurrentUserId(): ?int {
    return SessionManager::get('user_id');
}

function validateAdminSession(): bool {
    return SessionManager::validateAdminSession();
}

// ======================================================
// INITIALIZE SESSION ON INCLUDE
// ======================================================
SessionManager::init();

// ======================================================
// END OF CONFIGURATION FILE
// ======================================================
?>
