<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

// Test mode - just check connection
if (!empty($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'SSE endpoint is working']);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Ngăn timeout
ini_set('default_socket_timeout', 0);
set_time_limit(0);

// Lấy timestamp cuối cùng client gửi
$lastTimestamp = (int)($_GET['last_update'] ?? 0);
$currentTime = time();

// Gửi heartbeat để giữ kết nối sống
echo ": heartbeat\n\n";
flush();

// Kiểm tra mỗi 2 giây trong 55 giây (tối đa)
$startTime = time();
$maxDuration = 55;
$lastCheckTime = $currentTime;

while (time() - $startTime < $maxDuration) {
    try {
        // Kiểm tra client còn kết nối không
        if (connection_aborted()) {
            break;
        }

        // Lấy số donation pending có status = 'pending'
        $pendingResult = Database::fetch(
            "SELECT COUNT(*) as count FROM donations WHERE status = 'pending' AND UNIX_TIMESTAMP(created_at) > ?",
            [$lastTimestamp]
        );

        $newCount = $pendingResult['count'] ?? 0;

        // Lấy donations mới nhất (trong 10 phút qua)
        if ($newCount > 0) {
            $newDonations = Database::fetchAll(
                "SELECT d.donation_id, d.item_name, d.quantity, u.name as donor_name, d.status, d.created_at 
                 FROM donations d
                 LEFT JOIN users u ON d.user_id = u.user_id
                 WHERE d.status = 'pending' AND UNIX_TIMESTAMP(d.created_at) > ?
                 ORDER BY d.created_at DESC
                 LIMIT 5",
                [$lastTimestamp]
            );

            // Format và gửi event
            $data = [
                'type' => 'new_donations',
                'count' => $newCount,
                'donations' => $newDonations,
                'timestamp' => $currentTime
            ];

            echo "data: " . json_encode($data) . "\n\n";
            flush();
            
            $lastCheckTime = $currentTime;
        }

        // Sleep 2 giây rồi kiểm tra lại
        sleep(2);
        $currentTime = time();

    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        break;
    }
}

// Gửi event đóng kết nối
echo "event: close\n";
echo "data: Connection closed\n\n";
flush();
exit;
?>
