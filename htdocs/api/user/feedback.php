<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $subject = trim((string)($payload['subject'] ?? ''));
    $content = trim((string)($payload['content'] ?? ''));
    $rating = (int)($payload['rating'] ?? 0);

    if ($content === '') {
        api_json(false, ['message' => 'Content is required.'], 400);
    }
    if ($rating < 1 || $rating > 5) {
        $rating = null;
    }

    try {
        Database::execute(
            "INSERT INTO feedback (user_id, name, email, subject, content, rating, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                (int)$user['user_id'],
                $user['name'] ?? null,
                $user['email'] ?? null,
                $subject !== '' ? $subject : null,
                $content,
                $rating
            ]
        );
        $fb_id = (int)Database::lastInsertId();
        logActivity((int)$user['user_id'], 'submit_feedback', "Submitted feedback #$fb_id");
        api_json(true, ['fb_id' => $fb_id]);
    } catch (Exception $e) {
        error_log('feedback create error: ' . $e->getMessage());
        api_json(false, ['message' => 'Failed to submit feedback.'], 500);
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

$totalRow = Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE user_id = ?", [(int)$user['user_id']]);
$totalItems = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $per_page));

$feedback = Database::fetchAll(
    "SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [(int)$user['user_id'], $per_page, $offset]
);

api_json(true, [
    'feedback' => $feedback,
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $totalPages,
    ]
]);
?>
