<?php
/**
 * ======================================================
 * ADMIN WITHDRAWALS.PHP - Withdrawals Management UI
 * Ludo Tournament Platform - Admin Withdrawal Dashboard
 * Version: 2.0.0
 * ======================================================
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once dirname(__DIR__) . '/config/db.php';

SessionManager::init();

$isAdminLoggedIn = false;

if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_token'])) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, username, is_admin, is_active 
            FROM users 
            WHERE id = :admin_id 
            AND is_admin = 1 
            AND is_active = 1
        ");
        $stmt->execute([':admin_id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && $_SESSION['admin_token'] === hash('sha256', $admin['id'] . $admin['username'] . 'admin_secret')) {
            $isAdminLoggedIn = true;
        }
    } catch (Exception $e) {
        $isAdminLoggedIn = false;
    }
}

if (!$isAdminLoggedIn) {
    header('Location: index.php');
    exit;
}

$csrf_token = CSRFToken::generate();
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Management - Admin Dashboard</title>
    <style>
        /* ==============================================
           WITHDRAWAL ADMIN STYLES
           ============================================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0a0e1a;
            color: #f1f5f9;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .admin-header h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .admin-header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .admin-header-actions a {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 8px 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .admin-header-actions a:hover {
            background: rgba(255, 255, 255, 0.04);
        }
        
        .admin-header-actions a.logout {
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.2);
        }
        
        .admin-header-actions a.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: #1a1a2e;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            text-align: center;
        }
        
        .stat-card .stat-number {
            font-size: 24px;
            font-weight: 800;
        }
        
        .stat-card .stat-label {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .stat-card .stat-number.pending { color: #f59e0b; }
        .stat-card .stat-number.processing { color: #3b82f6; }
        .stat-card .stat-number.approved { color: #8b5cf6; }
        .stat-card .stat-number.completed { color: #10b981; }
        .stat-card .stat-number.rejected { color: #ef4444; }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            background: transparent;
            color: #94a3b8;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.04);
            color: #f1f5f9;
        }
        
        .filter-btn.active {
            background: rgba(124, 58, 237, 0.2);
            color: #8b5cf6;
            border-color: rgba(124, 58, 237, 0.2);
        }
        
        /* Withdrawal List */
        .withdrawal-list {
            display: grid;
            gap: 16px;
        }
        
        .withdrawal-card {
            background: #1a1a2e;
            border-radius: 14px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            transition: border-color 0.2s;
        }
        
        .withdrawal-card:hover {
            border-color: rgba(255, 255, 255, 0.08);
        }
        
        .withdrawal-card .wd-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .withdrawal-card .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .withdrawal-card .user-name {
            font-size: 18px;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .withdrawal-card .user-detail {
            font-size: 13px;
            color: #94a3b8;
        }
        
        .withdrawal-card .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .status-badge.processing { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .status-badge.approved { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        .
