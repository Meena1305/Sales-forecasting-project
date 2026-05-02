<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$loggedInUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest';
$userRole = $_SESSION['role'] ?? 'User';

$nameParts = explode(' ', $loggedInUser);
$userInitials = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) {
    $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
}

// Helper function to format date safely
function formatDateSafe($date) {
    if (empty($date)) return 'N/A';
    
    if ($date instanceof DateTime) {
        return $date->format('d M Y H:i');
    }
    
    if (is_string($date)) {
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('d M Y H:i', $timestamp);
        }
    }
    
    return 'N/A';
}

// Get notifications from database
$notifications = [];

if ($conn) {
    $query = "SELECT TOP 50 id, title, message, type, created_at, is_read 
              FROM Notifications 
              ORDER BY created_at DESC";
    $stmt = sqlsrv_query($conn, $query);
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $notifications[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'],
                'created_at' => formatDateSafe($row['created_at']),
                'is_read' => $row['is_read']
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
}

// Handle mark as read
if (isset($_POST['mark_read']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    if ($conn) {
        $updateQuery = "UPDATE Notifications SET is_read = 1 WHERE id = ?";
        $updateStmt = sqlsrv_query($conn, $updateQuery, [$id]);
    }
    header("Location: notifications.php");
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    if ($conn) {
        $updateQuery = "UPDATE Notifications SET is_read = 1";
        sqlsrv_query($conn, $updateQuery);
    }
    header("Location: notifications.php");
    exit();
}

// Handle clear all
if (isset($_POST['clear_all'])) {
    if ($conn) {
        $deleteQuery = "DELETE FROM Notifications";
        sqlsrv_query($conn, $deleteQuery);
    }
    header("Location: notifications.php");
    exit();
}

$unreadCount = count(array_filter($notifications, function($n) { return $n['is_read'] == 0; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - InsightSphere</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 1.5rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: #64748b;
        }
        .btn:hover {
            background: #f8fafc;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .notification-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            gap: 12px;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #f8fafc;
        }
        .notification-item.unread {
            background: #fefce8;
            border-left: 3px solid #f59e0b;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-icon.info {
            background: #dbeafe;
            color: #3b82f6;
        }
        .notification-icon.alert {
            background: #fee2e2;
            color: #ef4444;
        }
        .notification-icon.stock {
            background: #fed7aa;
            color: #f97316;
        }
        .notification-icon.customer {
            background: #d1fae5;
            color: #10b981;
        }
        .notification-content {
            flex: 1;
        }
        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .notification-message {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 6px;
        }
        .notification-time {
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .mark-read-btn {
            background: none;
            border: 1px solid #e2e8f0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            cursor: pointer;
            color: #64748b;
        }
        .mark-read-btn:hover {
            background: #f1f5f9;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .user-name {
            font-weight: 500;
            color: #0f172a;
        }
        .user-role {
            font-size: 0.7rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="indexHome.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Back to Dashboard
        </a>
        
        <div class="header">
            <h1>
                <i class='bx bx-bell'></i> 
                Notifications
                <?php if ($unreadCount > 0): ?>
                    <span style="background: #ef4444; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem;"><?php echo $unreadCount; ?> new</span>
                <?php endif; ?>
            </h1>
            <div class="header-actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn">
                        <i class='bx bx-check-double'></i> Mark all as read
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_all" class="btn" onclick="return confirm('Clear all notifications?')">
                        <i class='bx bx-trash'></i> Clear all
                    </button>
                </form>
            </div>
        </div>
        
        <div class="user-info">
            <div class="user-avatar"><?php echo $userInitials; ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($loggedInUser); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class='bx bx-bell-off'></i>
                    <p>No notifications yet</p>
                    <p style="font-size: 0.8rem; margin-top: 8px;">When you upload data, notifications will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="notification-icon <?php echo $notif['type']; ?>">
                            <i class='bx bx-<?php 
                                echo $notif['type'] == 'alert' ? 'error-circle' : 
                                     ($notif['type'] == 'stock' ? 'package' : 
                                     ($notif['type'] == 'customer' ? 'user-plus' : 'info-circle')); 
                            ?>'></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notification-time"><?php echo $notif['created_at']; ?></div>
                        </div>
                        <?php if ($notif['is_read'] == 0): ?>
                            <form method="POST" style="align-self: center;">
                                <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" name="mark_read" class="mark-read-btn">
                                    Mark read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>