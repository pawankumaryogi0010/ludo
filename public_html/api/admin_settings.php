<?php
/**
 * ======================================================
 * ADMIN_SETTINGS.PHP - System Settings API Handler
 * Ludo Tournament Platform - Admin Settings Management
 * Version: 2.0.0
 * ======================================================
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Include configuration
require_once dirname(__DIR__) . '/config/db.php';

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ==============================================
// CORS Headers
// ==============================================
header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==============================================
// AUTHENTICATION & AUTHORIZATION
// ==============================================
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_token'])) {
    jsonResponse(false, 'Unauthorized - Admin access required', [], 401);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify admin status
    $stmt = $conn->prepare("
        SELECT id, username, is_admin, is_active 
        FROM users 
        WHERE id = :admin_id 
        AND is_admin = 1 
        AND is_active = 1
    ");
    $stmt->execute([':admin_id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        jsonResponse(false, 'Unauthorized - Admin access required', [], 401);
    }
    
    // Verify admin token
    if ($_SESSION['admin_token'] !== hash('sha256', $admin['id'] . $admin['username'] . 'admin_secret')) {
        jsonResponse(false, 'Invalid session - Please login again', [], 401);
    }
    
} catch (Exception $e) {
    jsonResponse(false, 'Authentication error: ' . $e->getMessage(), [], 500);
}

// ==============================================
// ROUTING
// ==============================================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_settings':
        handleGetSettings();
        break;
    case 'update_settings':
        handleUpdateSettings();
        break;
    case 'toggle_maintenance':
        handleToggleMaintenance();
        break;
    case 'get_maintenance_status':
        handleGetMaintenanceStatus();
        break;
    default:
        jsonResponse(false, 'Invalid action specified', [], 400);
        break;
}

// ==============================================
// HANDLER: Get All Settings
// ==============================================
function handleGetSettings() {
    global $db, $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                setting_key,
                setting_value,
                setting_group,
                setting_type,
                description
            FROM system_settings
            ORDER BY setting_group, setting_key
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format settings by group
        $groupedSettings = [];
        foreach ($settings as $setting) {
            $group = $setting['setting_group'];
            if (!isset($groupedSettings[$group])) {
                $groupedSettings[$group] = [];
            }
            
            // Convert value based on type
            $value = $setting['setting_value'];
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'decimal':
                    $value = (float)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
                default:
                    // string or text - keep as is
                    break;
            }
            
            $groupedSettings[$group][] = [
                'key' => $setting['setting_key'],
                'value' => $value,
                'type' => $setting['setting_type'],
                'description' => $setting['description']
            ];
        }
        
        jsonResponse(true, 'Settings retrieved successfully', [
            'settings' => $groupedSettings,
            'raw' => $settings
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
    }
}

// ==============================================
// HANDLER: Update Settings
// ==============================================
function handleUpdateSettings() {
    global $db, $conn;
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['settings']) || !is_array($input['settings'])) {
        jsonResponse(false, 'Invalid settings data', [], 400);
    }
    
    // Validate CSRF token
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRFToken::validate($csrfToken)) {
        jsonResponse(false, 'Invalid CSRF token', [], 403);
    }
    
    try {
        $db->beginTransaction();
        
        $updatedCount = 0;
        $errors = [];
        
        foreach ($input['settings'] as $key => $value) {
            // Validate setting key exists
            $stmt = $conn->prepare("
                SELECT setting_key, setting_type, is_editable 
                FROM system_settings 
                WHERE setting_key = :key
            ");
            $stmt->execute([':key' => $key]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$setting) {
                $errors[] = "Setting '{$key}' not found";
                continue;
            }
            
            if (!$setting['is_editable']) {
                $errors[] = "Setting '{$key}' is not editable";
                continue;
            }
            
            // Validate and convert value based on type
            $validatedValue = validateSettingValue($value, $setting['setting_type']);
            if ($validatedValue === false) {
                $errors[] = "Invalid value for setting '{$key}'";
                continue;
            }
            
            // Update setting
            $stmt = $conn->prepare("
                UPDATE system_settings 
                SET setting_value = :value,
                    updated_at = CURRENT_TIMESTAMP
                WHERE setting_key = :key
            ");
            $stmt->execute([
                ':value' => (string)$validatedValue,
                ':key' => $key
            ]);
            
            if ($stmt->rowCount() > 0) {
                $updatedCount++;
            }
        }
        
        // Log the action
        $logEntry = [
            'action' => 'settings_updated',
            'admin_id' => $_SESSION['admin_id'],
            'settings' => array_keys($input['settings']),
            'updated_count' => $updatedCount,
            'errors' => $errors
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO maintenance_logs (action, details, admin_id, ip_address, created_at)
            VALUES (:action, :details, :admin_id, :ip, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':action' => 'settings_updated',
            ':details' => json_encode($logEntry),
            ':admin_id' => $_SESSION['admin_id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $db->commit();
        
        jsonResponse(true, 'Settings updated successfully', [
            'updated_count' => $updatedCount,
            'errors' => $errors
        ]);
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        jsonResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        jsonResponse(false, 'Error: ' . $e->getMessage(), [], 500);
    }
}

// ==============================================
// HANDLER: Toggle Maintenance Mode
// ==============================================
function handleToggleMaintenance() {
    global $db, $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['enable'])) {
        jsonResponse(false, 'Missing enable parameter', [], 400);
    }
    
    $enable = (bool)$input['enable'];
    $message = $input['message'] ?? 'We are currently performing scheduled maintenance. Please check back later.';
    
    // Validate CSRF token
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRFToken::validate($csrfToken)) {
        jsonResponse(false, 'Invalid CSRF token', [], 403);
    }
    
    try {
        $db->beginTransaction();
        
        // Update maintenance mode
        $stmt = $conn->prepare("
            UPDATE system_settings 
            SET setting_value = :value 
            WHERE setting_key = 'maintenance_mode'
        ");
        $stmt->execute([':value' => $enable ? '1' : '0']);
        
        // Update maintenance message
        if ($enable) {
            $stmt = $conn->prepare("
                UPDATE system_settings 
                SET setting_value = :message 
                WHERE setting_key = 'maintenance_message'
            ");
            $stmt->execute([':message' => $message]);
        }
        
        // Log the action
        $logEntry = [
            'action' => $enable ? 'maintenance_enabled' : 'maintenance_disabled',
            'admin_id' => $_SESSION['admin_id'],
            'message' => $message
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO maintenance_logs (action, details, admin_id, ip_address, created_at)
            VALUES (:action, :details, :admin_id, :ip, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':action' => $logEntry['action'],
            ':details' => json_encode($logEntry),
            ':admin_id' => $_SESSION['admin_id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $db->commit();
        
        jsonResponse(true, $enable ? 'Maintenance mode enabled' : 'Maintenance mode disabled', [
            'maintenance_mode' => $enable,
            'message' => $message
        ]);
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        jsonResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
    }
}

// ==============================================
// HANDLER: Get Maintenance Status
// ==============================================
function handleGetMaintenanceStatus() {
    global $db, $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                setting_key,
                setting_value
            FROM system_settings 
            WHERE setting_key IN ('maintenance_mode', 'maintenance_message')
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [
            'maintenance_mode' => false,
            'maintenance_message' => ''
        ];
        
        foreach ($results as $row) {
            if ($row['setting_key'] === 'maintenance_mode') {
                $data['maintenance_mode'] = (bool)$row['setting_value'];
            } elseif ($row['setting_key'] === 'maintenance_message') {
                $data['maintenance_message'] = $row['setting_value'];
            }
        }
        
        jsonResponse(true, 'Maintenance status retrieved', $data);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
    }
}

// ==============================================
// HELPER: Validate Setting Value by Type
// ==============================================
function validateSettingValue($value, $type) {
    switch ($type) {
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'integer':
            $int = filter_var($value, FILTER_VALIDATE_INT);
            return $int !== false ? $int : false;
        case 'decimal':
            $float = filter_var($value, FILTER_VALIDATE_FLOAT);
            return $float !== false ? $float : false;
        case 'json':
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : false;
        case 'string':
        case 'text':
            return (string)$value;
        default:
            return $value;
    }
}
?>
