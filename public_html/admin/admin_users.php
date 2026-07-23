<?php
/**
 * ======================================================
 * ADMIN_USERS.PHP - User Management UI
 * Ludo Tournament Platform - Admin User Management
 * Version: 2.0.0
 * ======================================================
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once dirname(__DIR__) . '/config/db.php';

SessionManager::init();

// ==============================================
// SECURE SESSION VALIDATION
// ==============================================
function validateAdminSession() {
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

if (!validateAdminSession()) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$csrf_token = CSRFToken::generate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <style>
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
        
        /* Search & Filter */
        .search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .search-bar input {
            flex: 1;
            min-width: 200px;
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: #f1f5f9;
            font-size: 14px;
            font-family: inherit;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #7c3aed;
        }
        
        .search-bar input::placeholder {
            color: #64748b;
        }
        
        .search-bar select {
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: #f1f5f9;
            font-size: 14px;
            font-family: inherit;
            cursor: pointer;
        }
        
        .search-bar select:focus {
            outline: none;
            border-color: #7c3aed;
        }
        
        .search-bar select option {
            background: #1a1a2e;
        }
        
        .search-bar .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-action.primary {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        .btn-action.primary:hover {
            background: rgba(59, 130, 246, 0.3);
        }
        
        /* Table */
        .table-container {
            background: #1a1a2e;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            overflow: hidden;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        table thead {
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        table th {
            padding: 12px 16px;
            text-align: left;
            color: #94a3b8;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            user-select: none;
        }
        
        table th:hover {
            color: #f1f5f9;
        }
        
        table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
        }
        
        table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge.active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .status-badge.inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .status-badge.verified { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .status-badge.rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .status-badge.not_submitted { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
        
        .btn-action-sm {
            padding: 4px 10px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            margin: 0 2px;
        }
        
        .btn-action-sm.primary { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .btn-action-sm.primary:hover { background: rgba(59, 130, 246, 0.3); }
        .btn-action-sm.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .btn-action-sm.success:hover { background: rgba(16, 185, 129, 0.3); }
        .btn-action-sm.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .btn-action-sm.danger:hover { background: rgba(239, 68, 68, 0.3); }
        .btn-action-sm.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .btn-action-sm.warning:hover { background: rgba(245, 158, 11, 0.3); }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-box {
            background: #1a1a2e;
            padding: 32px;
            border-radius: 16px;
            max-width: 700px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.06);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-box h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .modal-box .form-group {
            margin-bottom: 14px;
        }
        
        .modal-box .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        
        .modal-box .form-group input,
        .modal-box .form-group select,
        .modal-box .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: #f1f5f9;
            font-size: 14px;
            font-family: inherit;
        }
        
        .modal-box .form-group input:focus,
        .modal-box .form-group select:focus,
        .modal-box .form-group textarea:focus {
            outline: none;
            border-color: #7c3aed;
        }
        
        .modal-box .modal-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 12px 0;
        }
        
        .modal-box .detail-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .modal-box .detail-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .modal-box .detail-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #f1f5f9;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .modal-actions .btn-confirm {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1a1a2e;
        }
        
        .modal-actions .btn-confirm:hover {
